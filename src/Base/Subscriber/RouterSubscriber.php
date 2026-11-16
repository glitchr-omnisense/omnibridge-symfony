<?php

namespace Base\Subscriber;

use Base\Service\ParameterBagInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RouterSubscriber implements EventSubscriberInterface
{
    /**
     * @var RouterInterface
     */
    protected RouterInterface $router;

    /**
     * @var AuthorizationCheckerInterface
     */
    protected AuthorizationCheckerInterface $authorizationChecker;

    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker, RouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->router = $router;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Native Symfony debug commands (`debug:router`, `router:match`) build
     * their own matcher directly off Route::getHost() / RouteCollection —
     * they never go through the `matcher_class`/`generator_class` options
     * AdvancedRouter wires onto the `router` service, so host-templated
     * routes (see Base\Attributes\Attribute\Route) always show/match their
     * literal, unsubstituted sentinel tokens under these two commands. Since
     * they read straight off the shared RouteCollection instance, resolving
     * the tokens on it before the command runs (this process exits right
     * after, so nothing else ever sees the mutated Route objects) fixes
     * both commands using the exact same fallback resolution real request
     * handling uses.
     */
    private const HOST_TEMPLATED_COMMANDS = ['debug:router', 'router:match'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
            ConsoleEvents::COMMAND => ['onCommand', 2048],
        ];
    }

    public function onCommand(ConsoleEvent $event): void
    {
        if (!in_array($event->getCommand()?->getName(), self::HOST_TEMPLATED_COMMANDS, true)) {
            return;
        }

        foreach ($this->router->getRouteCollection()->all() as $route) {
            $host = $route->getHost();
            if ($host === '' || !str_contains($host, '\{_')) {
                continue;
            }

            $route->setHost($this->resolveHostTokens($host));
        }
    }

    private function resolveHostTokens(string $host): string
    {
        $machine = $this->router->getMachine();
        $subdomain = $this->router->getSubdomain();
        $domain = $this->router->getDomain();
        $port = $this->router->getPort();

        $host = str_replace('\{_machine\}.', $machine ? $machine . '.' : '', $host);
        $host = str_replace('\{_subdomain\}.', $subdomain ? $subdomain . '.' : '', $host);
        $host = str_replace('\{_domain\}', $domain ?? '', $host);
        $host = str_replace(':\{_port\}', $port ? ':' . $port : '', $host);

        return $host;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $this->router->getRoute();
        if (!$route) {
            return;
        }

        $url = get_url();

        $ipRestriction = !$this->parameterBag->get("base.router.ip_access") && $this->authorizationChecker->isGranted("VALIDATE_IP", $route);
        $reductionRequired = $this->router->reducesOnFallback();

        if ($ipRestriction) {

            $ipFallback = array_key_exists("ip", parse_url2($this->router->getHostFallback()));
            if (!$this->parameterBag->get("base.router.ip_access") && $ipFallback) {
                throw new \LogicException("IP access is disallowed and your fallback is an IP address. Either change your fallback `HTTP_DOMAIN` or turn on `base.router.ip_access`");
            }

            $parsedUrl = parse_url2(get_url());
            $parsedUrl["scheme"] = $this->router->getScheme();
	        $parsedUrl["host"] = $this->router->getHostFallback();
            $parsedUrl["port"] = $this->router->getPortFallback();


            $url = compose_url(
                 $parsedUrl["scheme"] ?? null,
                 null,
                 null,
                 null,
                 null,
                 $parsedUrl["host"] ?? null,
                 $parsedUrl["port"] ?? null,
                 $parsedUrl["path"] ?? null,
                 $parsedUrl["query"] ?? null,
                 $parsedUrl["fragment"] ?? null
             );

        } elseif ($reductionRequired) {

            $parsedUrl = parse_url2(get_url());
            $parsedUrl["machine"] = $this->router->getMachine() ?? null;
            $parsedUrl["subdomain"] = $this->router->getSubdomain() ?? null;
            $parsedUrl["domain"] = $this->router->getDomain() ?? null;
            $parsedUrl["port"] = $this->router->getPort() ?? null;
            
            $url = compose_url(
                $parsedUrl["scheme"] ?? null,
                null,
                null,
                $parsedUrl["machine"] ?? null,
                $parsedUrl["subdomain"] ?? null,
                $parsedUrl["domain"] ?? null,
                $parsedUrl["port"] ?? null,
                $parsedUrl["path"] ?? null,
                $parsedUrl["query"] ?? null,
                $parsedUrl["fragment"] ?? null
            );
        }

        // Redirect to sanitized url
        if ($url != get_url("http") && $url != get_url("https")) {
            $event->setResponse(new RedirectResponse($url));
            $event->stopPropagation();
        }
    }
}
