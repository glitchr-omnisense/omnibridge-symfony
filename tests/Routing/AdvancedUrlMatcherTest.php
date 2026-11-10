<?php

namespace Omnibridge\Symfony\Tests\Routing;

use Omnibridge\Symfony\Omnibase\Routing\Matcher\AdvancedUrlMatcher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * omnibase's matcher under Symfony answers a request whose address matches a route its method does not allow as
 * Symfony's own matcher does: MethodNotAllowedException with the allowed methods, which the router listener turns into
 * a 405 with `Allow`. It used to catch every exception of the matcher and retry the address with a trailing slash,
 * whose ResourceNotFoundException then made the answer a 404 (omnibridge/parity's former `method-not-allowed`).
 * match() on an address alone keeps answering "no route" for it: its callers (breadcrumbs, the localizer) ask whether
 * an address is a page, not what a request gets.
 */
#[Group('without-omniauth')]
final class AdvancedUrlMatcherTest extends TestCase
{
    private function matcher(string $method = 'GET'): AdvancedUrlMatcher
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/'));
        $routes->add('preview', new Route('/preview', methods: ['POST']));
        $routes->add('note', new Route('/notes/{slug}', methods: ['GET', 'HEAD']));
        $routes->add('note_edit', new Route('/notes/{slug}/edit', methods: ['PUT', 'PATCH']));
        $routes->add('folder', new Route('/folder/'));

        $previous = AdvancedUrlMatcher::$router;
        AdvancedUrlMatcher::$router = null;
        try {
            return new AdvancedUrlMatcher((new CompiledUrlMatcherDumper($routes))->getCompiledRoutes(), new RequestContext('', $method));
        } finally {
            AdvancedUrlMatcher::$router = $previous;
        }
    }

    public function testARequestWhoseMethodTheRouteRefusesIsMethodNotAllowed(): void
    {
        try {
            $this->matcher()->matchRequest(Request::create('/preview', 'GET'));
            self::fail('GET /preview matched a POST-only route');
        } catch (MethodNotAllowedException $e) {
            self::assertSame(['POST'], $e->getAllowedMethods());
        }
    }

    public function testADynamicRouteGivesItsAllowedMethodsToo(): void
    {
        try {
            $this->matcher()->matchRequest(Request::create('/notes/first/edit', 'POST'));
            self::fail('POST /notes/first/edit matched a PUT/PATCH-only route');
        } catch (MethodNotAllowedException $e) {
            self::assertSame(['PUT', 'PATCH'], $e->getAllowedMethods());
        }
    }

    public function testTheAllowedMethodStillMatches(): void
    {
        self::assertSame('preview', $this->matcher()->matchRequest(Request::create('/preview', 'POST'))['_route']);
        self::assertSame('note', $this->matcher()->matchRequest(Request::create('/notes/first', 'GET'))['_route']);
        self::assertSame('first', $this->matcher()->matchRequest(Request::create('/notes/first', 'HEAD'))['slug']);
    }

    public function testAnAddressNoRouteMatchesIsStillNotFound(): void
    {
        $this->expectException(ResourceNotFoundException::class);
        $this->matcher()->matchRequest(Request::create('/no-such-page', 'GET'));
    }

    public function testTheTrailingSlashRetryStillMatches(): void
    {
        self::assertSame('folder', $this->matcher()->matchRequest(Request::create('/folder', 'GET'))['_route']);
        self::assertSame('folder', $this->matcher()->match('/folder')['_route']);
    }

    public function testMatchingAnAddressAloneKeepsAnsweringNoRoute(): void
    {
        // A breadcrumb or the localizer asking, during a GET, about an address only POST reaches: no page there, as
        // before (their callers catch ResourceNotFoundException).
        $this->expectException(ResourceNotFoundException::class);
        $this->matcher('GET')->match('/preview');
    }

    public function testTheRequestIsForgottenAfterARefusal(): void
    {
        $matcher = $this->matcher();
        try {
            $matcher->matchRequest(Request::create('/preview', 'GET'));
        } catch (MethodNotAllowedException) {
        }

        $this->expectException(ResourceNotFoundException::class);
        $matcher->match('/preview');
    }
}
