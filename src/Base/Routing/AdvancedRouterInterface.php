<?php

namespace Base\Routing;

use Base\Contract\MediaRouter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface as SymfonyRouterInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\EventDispatcher\Event;

interface AdvancedRouterInterface extends SymfonyRouterInterface, RequestMatcherInterface, WarmableInterface, MediaRouter
{
    public function getRouteIndex(): string;

    public function getUrlIndex(): string;

    public function useAdvancedFeatures(): bool;

    public function isProfiler(mixed $request = null): bool;

    public function isUX(mixed $request = null): bool;
    public function isAPI(mixed $request = null): bool;
    public function isMainApplication(mixed $request = null): bool;

    public function isWdt(mixed $request = null): bool;

    public function isSecured(mixed $request = null): bool;

    public function reducesOnFallback(?string $locale = null, ?string $environment = null): bool;

    public function getBaseDir(?string $locale = null, ?string $environment = null): string;

    public function getHost(?string $locale = null, ?string $environment = null): string;

    public function getHostFallback(?string $locale = null, ?string $environment = null): string;

    public function getMachine(?string $locale = null, ?string $environment = null): ?string;

    public function getMachineFallback(?string $locale = null, ?string $environment = null): mixed;

    public function getMachineFallbacks(?string $locale = null, ?string $environment = null): ?array;

    public function getScheme(?string $locale = null, ?string $environment = null): string;
    
    public function getSubdomain(?string $locale = null, ?string $environment = null): ?string;

    public function getSubdomainFallback(?string $locale = null, ?string $environment = null): mixed;

    public function getSubdomainFallbacks(?string $locale = null, ?string $environment = null): ?array;

    public function getDomain(?string $locale = null, ?string $environment = null): ?string;

    public function getDomainFallback(?string $locale = null, ?string $environment = null): mixed;

    public function getDomainFallbacks(?string $locale = null, ?string $environment = null): ?array;

    public function getPort(?string $locale = null, ?string $environment = null): ?int;

    public function getPortFallback(?string $locale = null, ?string $environment = null): mixed;

    public function getPortFallbacks(?string $locale = null, ?string $environment = null): ?array;

    public function getUrl(?string $nameOrUrl = null, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string;

    public function getAssetUrl(string $nameOrUrl, ?string $packageName = null): string;

    public function getRequest(): ?Request;

    public function getEnvironment(): string;

    public function getLocale(?string $locale = null): string;

    public function getLocaleLang(?string $lang = null): string;

    public function getContext(): RequestContext;

    /**
     * @param RequestContext $context
     * @return mixed
     */
    public function setContext(RequestContext $context): void;

    public function getGenerator(): UrlGeneratorInterface;

    public function getMatcher(): UrlMatcherInterface|RequestMatcherInterface;

    public function isCli(): bool;

    public function isDebug(): bool;

    public function isAdmin(mixed $request = null): bool;

    public function hasFirewall(?string $routeUrl = null): ?bool;

    public function getCache(): CacheInterface;

    /**
     * @return mixed
     */
    public function getCacheRoutes();

    public function getRoute(?string $routeNameOrUrl = null): ?Route;

    public function getRouteHash(string $routeNameOrUrl): string;

    public function getRouteName(?string $routeUrl = null): ?string;

    public function getRouteMatch(?string $routeUrl = null): ?array;

    public function getRouteGroups(string $routeNameOrUrl): array;

    public function getRouteFirewall(?string $routeUrl = null): ?FirewallConfig;

    public function redirect(string $urlOrRoute, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse;

    public function redirectToRoute(string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): RedirectResponse;

    public function redirectEvent(Event $event, string $routeName, array $routeParameters = [], int $state = 302, array $headers = []): bool;

    public function reloadRequest(?Request $request = null): RedirectResponse;
}
