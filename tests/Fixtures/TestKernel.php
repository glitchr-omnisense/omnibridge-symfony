<?php

namespace Omnibridge\Symfony\Tests\Fixtures;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Omnibridge\Symfony\OmnibridgeBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

/**
 * The smallest Symfony application binding the contracts: FrameworkBundle
 * (router, translator, CSRF on a mock session), SecurityBundle (an in-memory
 * user), TwigBundle (twig-bridge's functions, then omnibridge/twig's on the
 * contracts) and OmnibridgeBundle; without SecurityBundle when $security is false.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(string $environment = 'test', bool $debug = true, private readonly bool $security = true)
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        if ($this->security) {
            yield new SecurityBundle();
        }
        yield new TwigBundle();
        yield new OmnibridgeBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.($this->security ? '' : '-bare');
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        if (class_exists(InMemoryFilesystemAdapter::class)) {
            // A storage as league/flysystem-bundle declares one: a Flysystem operator tagged by its name.
            $container->services()->set('test.storage.uploads', Filesystem::class)
                ->args([inline_service(InMemoryFilesystemAdapter::class)])
                ->tag('flysystem.storage', ['storage' => 'memory.uploads']);
        }
        $container->extension('framework', [
            'secret' => 'omnibridge-secret',
            'test' => true,
            'router' => ['utf8' => true],
            'translator' => ['default_path' => __DIR__.'/translations', 'fallbacks' => ['en']],
            'default_locale' => 'en',
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => $this->security,
        ]);
        if ($this->security) {
            $container->extension('security', [
                'providers' => ['memory' => ['memory' => ['users' => ['ada' => ['password' => 'x', 'roles' => ['ROLE_ADMIN']]]]]],
                'firewalls' => ['main' => ['lazy' => true, 'provider' => 'memory']],
                'role_hierarchy' => ['ROLE_ADMIN' => ['ROLE_USER']],
            ]);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller('kernel::home');
        $routes->add('page', '/page/{slug}')->controller('kernel::home');
    }
}
