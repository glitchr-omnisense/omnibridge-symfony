<?php

namespace Base\Subscriber;

use App\Entity\User;
use Base\Database\Attribute\Vault;
use Base\Entity\User as BaseUser;
use Base\Entity\User\Notification;
use Base\Request\Rule\Integrity;
use Base\Service\ReferrerInterface;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use ErrorException;
use InvalidArgumentException;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Command\CacheClearCommand;
use Base\Routing\AdvancedRouterInterface;
use Base\Security\RescueFormAuthenticator;
use RuntimeException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use TypeError;

/**
 * The session's integrity on Symfony's kernel request, decided by omnibase's Integrity rule (step F5e of its
 * plan, slice 2). Registered by no configuration, on purpose, as before: it opens a session on every main
 * request, runs `bin/console cache:clear` inside a web request on a Doctrine TypeError, and signs the user
 * out whenever a check fails. The account's own check (its notifications a live Doctrine collection) is
 * Doctrine's, so this host's.
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
    protected ?string $secret;

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
        if (!$this->router->isMainApplication()) {
            return;
        }
        if (BaseBundle::getInstance()->isInvalid() && $event->isMainRequest()) {
            $this->clearProcess->mustRun();
            throw new \RuntimeException('Application integrity compromised, maybe cache needs to be refreshed ?');
        }
        $rule = new Integrity($this->secret, $this->vault);
        $session = $this->requestStack->getSession();
        if (!$session->get(Integrity::SESSION_DATABASE)) {
            $session->set(Integrity::SESSION_DATABASE, $this->getDoctrineChecksum());
        }
        if (!$session->get(Integrity::SESSION_SECRET)) {
            $session->set(Integrity::SESSION_SECRET, $rule->seal());
        }
        if (!$this->router->hasFirewall()) {
            return;
        }
        $user = $this->tokenStorage->getToken()?->getUser();
        $intact = $this->checkUserIntegrity() && $rule->secretIntact($session->get(Integrity::SESSION_SECRET))
            && Integrity::databaseIntact(null !== $user, $session->get(Integrity::SESSION_DATABASE), $this->getDoctrineChecksum());
        $decision = $rule->decide($intact, $user, $this->router->getRouteName());
        if ($decision->isContinue()) {
            return;
        }
        foreach ($decision->notices as [$key, $parameters, $importance]) {
            (new \App\Entity\User\Notification($key, $parameters))->send($importance);
        }
        $this->referrer->clear();
        $this->tokenStorage->setToken(null);
        $session->remove(Integrity::SESSION_SECRET);
        $session->remove(Integrity::SESSION_DATABASE);
        $cleared = new Response();
        foreach ($decision->cookies as [$name]) {
            $cleared->headers->clearCookie($name);
            $cleared->headers->clearCookie($name, '/', $this->router->getDomain());
        }
        $cleared->sendHeaders();
        $event->setResponse($this->router->redirectToRoute($decision->route));
        $event->stopPropagation();
    }

    /**
     * @return array|mixed|null
     */
    protected function getSecret()
    {
        $marshaller = $this->vault->getMarshaller();
        return $this->vault->seal($marshaller, $this->secret);
    }

    protected function getDoctrineChecksum()
    {
        $doctrine = $this->doctrine;

        return Integrity::checksum($doctrine->getConnection($doctrine->getDefaultConnectionName())->getParams());
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
