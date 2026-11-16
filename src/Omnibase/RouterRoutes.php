<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Routing\AdvancedRouterInterface;
use Base\Service\IconProvider;
use Omnibridge\Contract\Routes;

/**
 * The Routes contract under Symfony (plan "omnibase agnostique", C4b):
 * omnibase's advanced_router - the route collection's path of a route, the
 * name its matcher gives an address (getRouteName()) - and the IconProvider's
 * icons of a route, the answers an entity got through BaseTrait's getRouter()
 * and getIconProvider().
 */
final class RouterRoutes implements Routes
{
    /** @param Routes|null $elsewhere what answers the route names this router lacks (omnibase's base.router.fallback) */
    public function __construct(
        private readonly AdvancedRouterInterface $router,
        private readonly ?IconProvider $icons = null,
        private readonly ?Routes $elsewhere = null,
    ) {
    }

    public function pattern(string $name): ?string
    {
        return $this->router->getRouteCollection()->get($name)?->getPath() ?? $this->elsewhere?->pattern($name);
    }

    public function match(string $url): ?string
    {
        return $this->router->getRouteName($url) ?? $this->elsewhere?->match($url);
    }

    public function icons(string $name): ?array
    {
        return $this->icons?->getRouteIcons($name) ?? (null === $this->router->getRouteCollection()->get($name) ? $this->elsewhere?->icons($name) : null);
    }
}
