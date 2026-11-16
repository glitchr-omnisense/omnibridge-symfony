<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Contract\Urls;
use Omnibridge\Symfony\RouterUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * omnibase's base.router.fallback under Symfony: a route name this router lacks is answered by that service (its URLs
 * here, its routes by RouterRoutes), the others by the router; without it the router's refusal stands.
 */
final class RouterFallbackTest extends TestCase
{
    private function router(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('here', new Route('/here'));

        return new UrlGenerator($routes, new RequestContext('', 'GET', 'site.example'));
    }

    public function testANameThisRouterLacksIsAnsweredElsewhere(): void
    {
        $elsewhere = new class implements Urls {
            public function path(string $name, array $parameters = []): string
            {
                return 'https://other.example/'.$name;
            }

            public function url(string $name, array $parameters = []): string
            {
                return 'https://other.example/'.$name;
            }

            public function asset(string $path): string
            {
                return $path;
            }
        };
        $urls = new RouterUrls($this->router(), null, $elsewhere);

        self::assertSame('/here', $urls->path('here'));
        self::assertSame('http://site.example/here', $urls->url('here'));
        self::assertSame('https://other.example/app_preamble', $urls->path('app_preamble'));
        self::assertSame('https://other.example/app_preamble', $urls->url('app_preamble'));
    }

    public function testWithoutElsewhereTheRoutersRefusalStands(): void
    {
        $this->expectException(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
        (new RouterUrls($this->router()))->path('app_preamble');
    }

    public function testTheOptionIsGivenToTheRoutersAdapters(): void
    {
        $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        $container->register('Omnibridge\Symfony\RouterUrls');
        $container->register('Omnibridge\Symfony\Omnibase\RouterRoutes');
        foreach (['Omnibridge\Symfony\RouterUrls', 'Omnibridge\Symfony\Omnibase\RouterRoutes'] as $id) {
            \Base\DependencyInjection\HostConfiguration::routerFallback($container, 'App\Routing\OtherSite');
            self::assertSame('App\Routing\OtherSite', (string) $container->getDefinition($id)->getArgument('$elsewhere'));
        }
    }
}
