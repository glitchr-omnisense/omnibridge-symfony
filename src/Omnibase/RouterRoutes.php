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
    public function __construct(
        private readonly AdvancedRouterInterface $router,
        private readonly ?IconProvider $icons = null,
    ) {
    }

    public function pattern(string $name): ?string
    {
        return $this->router->getRouteCollection()->get($name)?->getPath();
    }

    public function match(string $url): ?string
    {
        return $this->router->getRouteName($url);
    }

    public function icons(string $name): ?array
    {
        return $this->icons?->getRouteIcons($name);
    }
}
