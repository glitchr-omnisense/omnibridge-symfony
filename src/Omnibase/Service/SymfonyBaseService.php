<?php

namespace Omnibridge\Symfony\Omnibase\Service;

use Base\Service\BaseService;
use Base\Service\IconProvider;
use Base\Service\LocalizerInterface;
use Base\Service\MediaService;
use Base\Service\Obfuscator;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBag;
use Base\Service\TradingInterface;
use Base\Service\TranslatorInterface;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Contract\Runtime;
use Exception;
use InvalidArgumentException;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment as EnvironmentContract;
use Omnibridge\Contract\Requests;
use Omnibridge\Contract\Urls;
use Omnibridge\Symfony\Omnibase\Database\MetadataManipulator as ClassMetadataManipulator;
use Omnibridge\Doctrine\Omnibase\DoctrineLocator;
use Omnibridge\Symfony\Omnibase\FrameworkLocator;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouterInterface;
use Base\Service\Model\DevelopmentEnvironment;
use Doctrine\Persistence\Event\PreUpdateEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\EventDispatcher\Event;
use Twig\Environment;

// The alias this class is typed on (the core's MetadataManipulator on 5.x, Doctrine's on 4.x), defined when this file
// is loaded: a type check never autoloads, and BaseBundle.php, which defines it too, is not every application's
// (a 4.x one registers the core's Omnibridge\Symfony\Omnibase\BaseBundle; plan "omnibase agnostic", step B3).
class_exists(ClassMetadataManipulator::class);

/**
 * omnisense/omnibase's BaseService under Symfony (its plan "omnibase agnostic", step F5c, item 3): the core's
 * facade is framework-neutral, this is the host's half - what only a Symfony kernel has. The container hands it
 * out as `base.service` and Base\Service\BaseService (a lazy service: a ghost of this class until a method reads
 * a property), with the 23 services it was given before, so that an application's code and templates keep every
 * method they called:
 * - the kernel and its container (getContainer(), getAvailableServices(), getParameter(), hasParameter(),
 *   setParameter()), its console (exec()), its profiler (getProfiler(), getProfile());
 * - the form factory (createForm()) and the CSRF token manager (isCsrfTokenValid() with a form and its request);
 * - Symfony's request, routes and responses (getRequest(), getRoute(), getRouteName(), redirect(),
 *   redirectToRoute(), refresh(), isAdmin(), isProfiler(), getRouteIndex()) through omnibase's router;
 * - the security token (getToken()), the session object (getSession() without a name);
 * - Symfony's services where no contract answers: the session, the route, the address, the visitor, the
 *   authorization checker, Twig's asset rules, the kernel's environment and debug, Doctrine's original data
 *   (a closed entity manager reopened).
 */
class SymfonyBaseService extends BaseService
{
    /**
     * @var KernelInterface
     */
    private KernelInterface $kernel;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected AuthorizationCheckerInterface $authorizationChecker;

    /**
     * @var CsrfTokenManagerInterface
     */
    protected CsrfTokenManagerInterface $csrfTokenManager;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    protected ?Profiler $profiler = null;

    public function __construct(
        KernelInterface               $kernel,
        RequestStack                  $requestStack,
        FirewallMapInterface          $firewallMap,
        Environment                   $twig,
        SluggerInterface              $slugger,
        ?ManagerRegistry              $doctrine,
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface         $tokenStorage,
        CsrfTokenManagerInterface     $csrfTokenManager,
        ParameterBagInterface         $parameterBag,
        NotifierInterface             $notifier,
        FormFactoryInterface          $formFactory,
        LocalizerInterface            $localizer,
        TradingInterface              $tradingMarket,
        Obfuscator                    $obfuscator,
        SettingBag                    $settingBag,
        MediaService                  $mediaService,
        IconProvider                  $iconProvider,
        TranslatorInterface           $translator,
        AdvancedRouterInterface       $router,
        EntityHydratorInterface       $entityHydrator,
        ClassMetadataManipulator      $classMetadataManipulator,
        ?Profiler                     $profiler
    )
    {
        parent::__construct(null, $parameterBag, $kernel->getProjectDir(), $kernel->getStartTime());

        // Kernel and additional stopwatch
        $this->kernel = $kernel;
        $this->container = $kernel->getContainer();
        $this->setEnvironment($kernel->getEnvironment());

        $this->authorizationChecker = $authorizationChecker;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->formFactory = $formFactory;
        $this->profiler = $profiler;

        // Additional common containers
        $this->setClassMetadataManipulator($classMetadataManipulator);
        $this->setMediaService($mediaService);
        $this->setIconProvider($iconProvider);
        $this->setSettingBag($settingBag);
        $this->setLocalizer($localizer);
        $this->setTrading($tradingMarket);
        $this->setTwig($twig);
        $this->setRouter($router);
        $this->setFirewallMap($firewallMap);
        $this->setObfuscator($obfuscator);
        $this->setTranslator($translator);
        $this->setSlugger($slugger);
        $this->setDoctrine($doctrine);
        $this->setEntityHydrator($entityHydrator);
        $this->setRequestStack($requestStack);
        $this->setTokenStorage($tokenStorage);
        $this->setNotifier($notifier);
    }

    /**
     * @param $name
     * @return object|ContainerInterface|null
     */
    public function getContainer($name)
    {
        return ($name ? $this->container->get($name) : $this->container);
    }

    public function getAvailableServices(): array
    {
        if (!isset($this->container)) {
            throw new Exception("Symfony container not found in BaseService. Did you overloaded self::__construct ?");
        }

        return $this->container->getServiceIds();
    }

    public function getRouteIndex(): string
    {
        return FrameworkLocator::router()->getRouteIndex();
    }

    /**
     * @param string $command
     * @param array $arguments
     * @return Response
     * @throws Exception
     */
    public function exec(string $command, array $arguments = [])
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput(array_merge($arguments, ['command' => $command]));
        $output = new BufferedOutput();
        $application->run($input, $output);

        $content = $output->fetch();
        return new Response($content);
    }

    /** @return void */
    public function addSession($name, $value)
    {
        // The Requests contract where a host binds it (plan "omnibase agnostic", F5c item 3), Symfony's session else.
        if (($requests = self::requests())) {
            $requests->remember($name, $value);

            return;
        }
        $this->getSession()->set($name, $value);
    }

    /** @return mixed */
    public function removeSession($name)
    {
        if (self::requests()) {
            return parent::removeSession($name);
        }

        return (FrameworkLocator::requestStack() && FrameworkLocator::requestStack()->getSession()->has($name)) ? FrameworkLocator::requestStack()->getSession()->remove($name) : null;
    }

    /**
     * @param $name
     * @return mixed|SessionInterface|null
     */
    public function getSession($name = null)
    {
        if (!$name) {
            // The host's session object itself: Symfony's (no contract hands one out).
            return FrameworkLocator::requestStack()->getSession();
        }
        if (self::requests()) {
            return parent::getSession($name);
        }
        return (FrameworkLocator::requestStack() && FrameworkLocator::requestStack()->getSession()->has($name)) ? FrameworkLocator::requestStack()->getSession()->get($name) : null;
    }

    /**
     * @param $type
     * @param $data
     * @param array $options
     * @return FormInterface
     */
    public function createForm($type, $data = null, array $options = []): FormInterface
    {
        return $this->formFactory->create($type, $data, $options);
    }

    public function getProfiler(): ?Profiler
    {
        return $this->profiler;
    }

    /**
     * @param $response
     * @return Profile|null
     */
    public function getProfile($response = null)
    {
        if (!$response) {
            return null;
        }
        return $this->getProfiler()->loadProfileFromResponse($response);
    }

    public function getParameter(string $name): array|bool|string|int|float|null
    {
        return $this->kernel->getContainer()->getParameter($name);
    }

    public function hasParameter(string $name): bool
    {
        return $this->kernel->getContainer()->hasParameter($name);
    }

    public function setParameter(string $name, array|bool|string|int|float|null $value)
    {
        $this->kernel->getContainer()->setParameter($name, $value);
    }

    public function getAsset(string $url): string
    {
        // omnibase's Twig environment under Symfony (its asset rules).
        return FrameworkLocator::twig()->getAsset($url);
    }

    public function getRequest(): ?Request
    {
        return FrameworkLocator::router()->getRequest();
    }

    public function getCurrentRequest(): ?Request
    {
        return $this->getRequest();
    }

    public function getRoute(?string $url): ?Route
    {
        return FrameworkLocator::router()->getRoute($url);
    }

    public function getCurrentRoute(): ?Route
    {
        return FrameworkLocator::router()->getRoute();
    }

    public function getRouteName(?string $url): ?string
    {
        return FrameworkLocator::router()->getRouteName($url);
    }

    public function getCurrentRouteName(): ?string
    {
        if (self::requests()) {
            return parent::getCurrentRouteName();
        }

        return FrameworkLocator::router()->getRouteName();
    }

    public function generateUrl(string $routeName, array $routeParameters = []): string
    {
        if (Runtime::find(Urls::class)) {
            return parent::generateUrl($routeName, $routeParameters);
        }

        return FrameworkLocator::router()->generate($routeName, $routeParameters);
    }

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse
    {
        if (filter_var($urlOrRoute, FILTER_VALIDATE_URL) || str_contains($urlOrRoute, "/")) {
            return new RedirectResponse($urlOrRoute);
        }
        return new RedirectResponse(FrameworkLocator::router()->generate($urlOrRoute, $routeParameters), $state, $headers);
    }

    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): ?RedirectResponse
    {
        $routeNameBak = $routeName;

        $event = null;
        if (array_key_exists("event", $headers)) {
            $event = $headers["event"];
            if (!($event instanceof Event)) {
                throw new InvalidArgumentException("header variable \"event\" must be " . Event::class . ", currently: " . (is_object($event) ? get_class($event) : gettype($event)));
            }
            unset($headers["event"]);
        }

        $exceptions = [];
        if (array_key_exists("exceptions", $headers)) {
            $exceptions = $headers["exceptions"];
            if (!is_string($exceptions) && !is_array($exceptions)) {
                throw new InvalidArgumentException("header variable \"exceptions\" must be of type \"array\" or \"string\", currently: " . (is_object($exceptions) ? get_class($exceptions) : gettype($exceptions)));
            }
            unset($headers["exceptions"]);
        }

        $callback = null;
        if (array_key_exists("callback", $headers)) {
            $callback = $headers["callback"];
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("header variable \"callback\" must be callable, currently: " . (is_object($callback) ? get_class($callback) : gettype($callback)));
            }

            unset($headers["callback"]);
        }

        $url = FrameworkLocator::router()->generate($routeName, $routeParameters) ?? $routeName;
        $routeName = $this->getRouteName($url);
        if (!$routeName) {
            throw new RouteNotFoundException(sprintf('Unable to generate a URL for the named route "%s" as such route does not exist.', $routeNameBak));
        }

        foreach ($exceptions as $pattern) {
            if (preg_match($pattern, $this->getCurrentRouteName())) {
                return null;
            }
        }

        $response = new RedirectResponse($url, $state, $headers);
        if ($event && method_exists($event, "setResponse")) {
            $event->setResponse($response);
        }

        // Callable action if redirection happens
        if (is_callable($callback)) {
            $callback();
        }

        return $response;
    }

    public function refresh(?Request $request = null): RedirectResponse
    {
        $request = $request ?? $this->getRequest();
        return $this->redirect($request->attributes->get('_route'));
    }

    /** @return bool */
    public function isDevelopment()
    {
        if ($environment = Runtime::find(EnvironmentContract::class)) {
            return DevelopmentEnvironment::is($environment);
        }

        return DevelopmentEnvironment::named($this->kernel->getEnvironment(), $this->kernel->isDebug());
    }

    /** @return bool */
    public function isDebug()
    {
        return Runtime::find(EnvironmentContract::class)?->isDebug() ?? $this->kernel->isDebug();
    }

    /**
     * @return bool
     */
    public function isAdmin()
    {
        return FrameworkLocator::router()->isAdmin();
    }

    /**
     * @return bool
     */
    public function isProfiler()
    {
        return FrameworkLocator::router()->isProfiler();
    }

    /**
     * @param string $id
     * @param $tokenOrForm
     * @param Request|null $request
     * @param string $csrfFieldId
     * @return bool
     * @throws Exception
     */
    public function isCsrfTokenValid(string $id, $tokenOrForm, $request = null, string $csrfFieldId = "_csrf_token"): bool
    {
        if (!isset($this->csrfTokenManager)) {
            throw new Exception("No CSRF token manager found in BaseService. Did you overloaded self::__construct ?");
        }

        // Prepare token parameter

        $token = null;
        if (!$tokenOrForm instanceof FormInterface) {
            $token = $tokenOrForm;
        } else {
            $form = $tokenOrForm;
            if (!$request instanceof Request) {
                throw new Exception("Request required as FormInterface provided");
            }

            //$form->handleRequest($request); // TBC
            if ($request->request->has($form->getName())) {
                $token = $request->request->get($form->getName())[$csrfFieldId] ?? null;
            }
        }

        // Handling CSRF token exception
        if ($token && !is_string($token)) {
            throw new Exception("Unexpected token value provided: string expected");
        }

        // Checking validity
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }

    /**
     * @return TokenInterface|null
     * @throws Exception
     */
    public function getToken()
    {
        if (FrameworkLocator::tokenStorage() === null) {
            throw new Exception("No token storage found in BaseService. Did you overloaded self::__construct ?");
        }

        return FrameworkLocator::tokenStorage()->getToken();
    }

    /**
     * @return UserInterface|null
     * @throws Exception
     */
    public function getUser()
    {
        if ($access = Runtime::find(Access::class)) {
            return $access->getUser();
        }

        if (!$token = $this->getToken()) {
            return null;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return null;
        }

        if (!$user instanceof UserInterface) {
            return null;
        }

        return $user;
    }

    public function isGranted($attribute, $subject = null): bool
    {
        if (Runtime::find(Access::class)) {
            return parent::isGranted($attribute, $subject);
        }

        if (!isset($this->authorizationChecker)) {
            throw new Exception("No authorization checker found in BaseService. Did you overloaded self::__construct ?");
        }

        return $this->authorizationChecker->isGranted($attribute, $subject);
    }

    public function inDoctrine($entity): bool
    {
        if (!is_object($entity)) {
            return false;
        }

        return (bool) (Runtime::find(\Omnibridge\Persistence\UnitOfWork::class)?->contains($entity) ?? FrameworkLocator::entityManager()?->contains($entity));
    }

    /**
     * Doctrine's original entity data, as before: a closed entity manager reopened with $reopen.
     *
     * @return mixed[]
     */
    public function getOriginalEntityData($eventOrEntity, bool $inDoctrineStack = false, bool $reopen = false)
    {
        $entity = $eventOrEntity->getObject();
        $originalEntityData = DoctrineLocator::entityManager($reopen)->getUnitOfWork()->getOriginalEntityData($entity);

        if ($eventOrEntity instanceof PreUpdateEventArgs) {
            $event = $eventOrEntity;
            foreach ($event->getEntityChangeSet() as $field => $data) {
                $originalEntityData[$field] = $data[0];
            }
        } elseif ($inDoctrineStack === true && $this->inDoctrineStack()) {
            throw new Exception("Achtung ! You are trying to access data object within a Doctrine method.." .
                "Original entity might have already been updated.");
        }

        return $originalEntityData;
    }
}
