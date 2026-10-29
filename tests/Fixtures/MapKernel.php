<?php

namespace Omnibridge\Symfony\Tests\Fixtures;

use Omnibridge\Symfony\OmnibridgeBundle;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Testing\PlainTiles;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

/**
 * omnimap under Symfony: OpenStreetMap as the page's provider, its tiles
 * answered here (PlainTiles: no network), the cache on the omnidrive drive
 * local.uploads (a local Flysystem storage in var/omnimap-test),
 * tiles written under var/omnimap-public, the routes at lesringostars'
 * addresses and names (ux-google's), an administrator in memory, and a page
 * printing the map and its buttons.
 */
final class MapKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new OmnibridgeBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/map';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    /** The tile server: every tile drawn here, every request counted. */
    public static function tiles(): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            ++self::$requests;
            self::$userAgents[] = $options['normalized_headers']['user-agent'][0] ?? '';
            if (!preg_match('~/(\d+)/(\d+)/(\d+)\.png$~', $url, $m)) {
                return new MockResponse('', ['http_code' => 404]);
            }

            return new MockResponse(PlainTiles::png((int) $m[1], (int) $m[2], (int) $m[3]), ['response_headers' => ['content-type' => 'image/png', 'cache-control' => 'max-age=91080']]);
        });
    }

    public static int $requests = 0;

    /** @var list<string> */
    public static array $userAgents = [];

    public static function view(): MapView
    {
        return new MapView(new LatLng(35.0116, 135.7681), 12, [new Marker(new LatLng(35.0036, 135.778), 'Gion')], controls: false, id: 'myMap');
    }

    public function page(Environment $twig): Response
    {
        return new Response($twig->createTemplate('<html><body>{{ omnimap(view) }}{{ omnimap_export(view, {class: "item", text: "up"}) }}{{ omnimap_suppress(view, {text: "x"}) }}</body></html>')->render(['view' => self::view()]));
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'omnibridge-secret',
            'test' => true,
            'router' => ['utf8' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
        ]);
        $container->extension('security', [
            'providers' => ['memory' => ['memory' => ['users' => ['ada' => ['password' => 'x', 'roles' => ['ROLE_ADMIN']]]]]],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'memory']],
        ]);
        $container->extension('omnimap', [
            'provider' => 'openstreetmap',
            'secret' => 'c2VjcmV0',
            'providers' => ['openstreetmap' => ['user_agent' => 'omnibridge-tests/1.0 (+https://github.com/glitchr-omnisense/omnibridge-symfony)'], 'vector' => ['factory' => 'maplibre']],
            'cache' => [
                'drive' => 'local.uploads',
                'directory' => 'google',
                'size' => '600x400',
                'pool' => 'cache.app',
                'public_directory' => '%kernel.project_dir%/var/omnimap-public',
                'public_size' => 500,
            ],
            'html' => ['class' => 'google-tilemap', 'suffix' => '_html2canvas'],
            'routes' => [
                'tile' => ['path' => '/google/{signature}/{id}.webp', 'name' => 'gm_show'],
                'metadata' => ['path' => '/google/maps/{signature}', 'name' => 'gm_show_metadata'],
                'export' => ['path' => '/google/maps/{signature}/export', 'name' => 'gm_export'],
                'suppress' => ['path' => '/google/maps/{signature}/suppress', 'name' => 'gm_suppress'],
            ],
        ]);
        $services = $container->services();
        // The uploads as league/flysystem-bundle declares a storage (a Flysystem operator tagged by its name): omnidrive's drive "local.uploads".
        $services->set('test.storage.uploads', Filesystem::class)
            ->args([inline_service(LocalFilesystemAdapter::class)->args(['%kernel.project_dir%/var/omnimap-test'])])
            ->tag('flysystem.storage', ['storage' => 'local.uploads']);
        $services->set('http_client', MockHttpClient::class)->factory([self::class, 'tiles'])->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('map_page', '/map')->controller('kernel::page');
        $routes->import('.', 'omnimap');
    }
}
