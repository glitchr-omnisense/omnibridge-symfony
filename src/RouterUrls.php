<?php

namespace Omnibridge\Symfony;

use Omnibridge\Contract\Urls;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Omnibridge\Contract\Urls under Symfony: the router (the application's, or
 * one that generates through it - omnibase gives its advanced_router) and the
 * asset packages - what Twig's path(), url() and asset() answer, the same
 * strings.
 *
 * Moved from omniscient/omnibase's Base\Bridge\Symfony\RouterUrls (plan
 * "omnibase agnostique", A1); the former name is an alias of this class.
 */
final class RouterUrls implements Urls
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly ?Packages $packages = null,
    ) {
    }

    public function path(string $name, array $parameters = []): string
    {
        return $this->router->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function url(string $name, array $parameters = []): string
    {
        return $this->router->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function asset(string $path): string
    {
        return $this->packages?->getUrl($path) ?? $path;
    }

    /**
     * Any of the router's reference types, beyond the contract (which has an
     * absolute path and URL only): omnibase's Base\Contract\Link::to() asks it
     * for a relative or a network path.
     */
    public function generate(string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->router->generate($name, $parameters, $referenceType);
    }
}
