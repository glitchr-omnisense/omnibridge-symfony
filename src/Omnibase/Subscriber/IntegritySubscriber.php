<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use Base\Database\Attribute\Vault;
use App\Entity\User;
use Base\Entity\User as BaseUser;

use Base\Entity\User\Notification;
use Base\Security\RescueFormAuthenticator;
use Base\BaseBundle;
use Omnibridge\Symfony\Omnibase\Console\Command;
use Omnibridge\Symfony\Omnibase\Console\Command\CacheClearCommand;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use Base\Service\ReferrerInterface;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\ORM\EntityNotFoundException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\Persistence\ManagerRegistry;
use ErrorException;
use InvalidArgumentException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Process\Process;
use TypeError;

/**
 * Registered by no configuration, on purpose: as written it would open a session on every main
 * request, run `bin/console cache:clear` inside a web request on a Doctrine TypeError, and sign
 * the user out (and clear the remember-me cookie) whenever one of its checks fails. A site that
 * wants these checks registers it itself, knowing that.
 */
class IntegritySubscriber implements EventSubscriberInterface
{
    public const int DEFAULT_PRIORITY = 7;

    /**
     * @var TokenStorageInterface
     */
    protected TokenStorageInterface $tokenStorage;

    /**
     * @var ManagerRegistry
     */
    protected ManagerRegistry $doctrine;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    /**
     * @Vault
     */
    protected Vault $vault;

    /**
     * @string
     */
    private ?string $secret;

    /**
     * @var ReferrerInterface
     */
    protected ReferrerInterface $referrer;

    /**
     * @Process
     */
    protected Process $clearProcess;

    public function __construct(TokenStorageInterface $tokenStorage, RequestStack $requestStack, ManagerRegistry $doctrine, AdvancedRouterInterface $router, ReferrerInterface $referrer, string $projectDir, ?string $secret = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        $this->doctrine = $doctrine;
        $this->router = $router;
        $this->referrer = $referrer;

        $this->clearProcess = new Process(['php', 'bin/console', 'cache:clear']);
        $this->clearProcess->setWorkingDirectory($projectDir);

        $this->secret = $secret;
        $this->vault = new Vault();
    }

    public static function getSubscribedEvents(): array
    {
        return
            [
                KernelEvents::EXCEPTION => ['onException', 7],
                ConsoleEvents::COMMAND => ['onCommand', 2048],
                RequestEvent::class => [
                    ['onEarlyKernelRequest', 2048], 
                    ['onKernelRequest', IntegritySubscriber::DEFAULT_PRIORITY]
                ]
            ];
    }

    public function checkCacheReady()
    {
        if(CacheClearCommand::applicationNotStarted()) {
            $this->clearProcess->mustRun();
            throw new RuntimeException("Application integrity compromised, cache clear not started yet.", 0);
        }
    }

    public function onCommand(ConsoleEvent $event)
    { 
        $command = $event->getCommand();
        if(!$command instanceof CacheClearCommand && $command instanceof Command) {
            $this->checkCacheReady();
        }
    }

    public function onEarlyKernelRequest()
    { 
        $this->checkCacheReady();
    }

    public function onException(ExceptionEvent $event)
    {
        $throwable = $event->getThrowable();

        $instanceOf = ($throwable instanceof TypeError || $throwable instanceof DoctrineException ||
            $throwable instanceof ErrorException || $throwable instanceof InvalidArgumentException ||
            $throwable instanceof EntityNotFoundException);

        $this->checkCacheReady();

        if ($instanceOf && check_backtrace("Doctrine", "UnitOfWork", $throwable->getTrace())) {
            // throw new RuntimeException("Application integrity compromised, maybe cache needs to be refreshed ?", 0, $throwable);
            $this->clearProcess->mustRun();
        }
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if(! $this->router->isMainApplication()) {
            return;
        }

        if (BaseBundle::getInstance()->isInvalid() && $event->isMainRequest()) {
            $this->clearProcess->mustRun();
            throw new RuntimeException("Application integrity compromised, maybe cache needs to be refreshed ?");
        }

        $token = $this->tokenStorage->getToken();

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/doctrine")) {
            $session->set("_integrity/doctrine", $this->getDoctrineChecksum());
        }
        if (!$session->get("_integrity/secret")) {
            $session->set("_integrity/secret", $this->getSecret());
        }

        if (!$this->router->hasFirewall()) {
            return;
        }
        if ($this->router->getRouteName() == RescueFormAuthenticator::LOGIN_ROUTE) {
            return;
        }

        $integrity  = $this->checkUserIntegrity();
        $integrity &= $this->checkSecretIntegrity();
        $integrity &= $this->checkDoctrineIntegrity();

        if (!$integrity) {

            if ($token) {
                $user = $token->getUser();
                $notification = new Notification("integrity", [$user]);
                $notification->send("danger");
            }

            $this->referrer->clear();

            $this->tokenStorage->setToken(null);

            $session = $this->requestStack->getSession();
            $session->remove("_integrity/secret");
            $session->remove("_integrity/doctrine");

            $response = new Response();
            $response->headers->clearCookie('REMEMBERME');
            $response->headers->clearCookie('REMEMBERME', "/", $this->router->getDomain());

            $response->sendHeaders();

            $response = $this->router->redirectToRoute(RescueFormAuthenticator::LOGIN_ROUTE);
            $event->setResponse($response);
            $event->stopPropagation();
        }
    }

    /**
     * @return array|mixed|null
     */
    protected function getSecret()
    {
        $marshaller = $this->vault->getMarshaller();
        return $this->vault->seal($marshaller, $this->secret);
    }

    /**
     * @return string
     */
    protected function getDoctrineChecksum()
    {
        $connection = $this->doctrine->getConnection($this->doctrine->getDefaultConnectionName());
        $params = $connection->getParams();

        $host = $params["host"] ?? "";
        if (!$host) {
            return "";
        }

        $driver = $params["driver"] ?? null;
        $driver = $driver ? $driver . "://" : "";

        $user = $params["user"] ?? null;
        $user = $user ? $user . "@" : "";

        $port = $params["port"] ?? null;
        $port = $port ? ":" . $port : "";

        $dbname = $params["dbname"] ?? null;
        $dbname = $dbname ? "/" . $dbname : "";

        $charset = $params["charset"] ?? null;
        $charset = $charset ? " (" . $params["charset"] . ")" : "";

        return md5($driver . $user . $host . $port . $dbname . $charset);
    }

    /**
     * @return bool
     */
    public function checkUserIntegrity()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return true;
        }

        $user = $token->getUser();
        if ($user === null) {
            return true;
        }
        if (!$user instanceof BaseUser) {
            return true;
        }

        $persistentCollection = ($user->getNotifications() instanceof PersistentCollection ? (array)$user->getNotifications() : null);
        if ($persistentCollection === null) {
            return false;
        }

        $dirtyCollection = [
            "\x00*\x00initialized" => false,
            "\x00Doctrine\ORM\PersistentCollection\x00snapshot" => [],
            "\x00Doctrine\ORM\PersistentCollection\x00owner" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00association" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00em" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00backRefFieldName" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00typeClass" => null,
            "\x00Doctrine\ORM\PersistentCollection\x00isDirty" => false,
        ];

        return array_intersect_key($persistentCollection, $dirtyCollection) !== $dirtyCollection;
    }


    /**
     * @return bool
     */
    public function checkDoctrineIntegrity()
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return true;
        }

        $user = $token->getUser();
        if ($user === null) {
            return true;
        }

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/doctrine")) {
            return false;
        }

        return $this->getDoctrineChecksum() == $session->get("_integrity/doctrine");
    }

    /**
     * @return bool
     */
    public function checkSecretIntegrity()
    {
        if ($this->secret == null) {
            return true;
        }

        $marshaller = $this->vault->getMarshaller();
        if ($marshaller == null) {
            return true;
        }

        $session = $this->requestStack->getSession();
        if (!$session->get("_integrity/secret")) {
            return false;
        }

        return $this->secret == $this->vault->reveal($marshaller, $session->get("_integrity/secret"));
    }
}
