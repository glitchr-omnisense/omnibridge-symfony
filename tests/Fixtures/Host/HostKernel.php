<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Base\DependencyInjection\Compiler\Pass\DoctrineConfigurationPass;
use Base\DependencyInjection\HostConfiguration;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * A minimal Symfony application whose configuration names no class: FrameworkBundle, SecurityBundle, DoctrineBundle
 * and `base:`. Its security.yaml's worth is the firewalls' names and patterns; its doctrine.yaml's a connection and
 * its entities' folder; its framework.yaml's nothing of omnibase. What Symfony was given is kept as parameters
 * (host_test.*) for the test to read.
 */
final class HostKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /** @param array<string, mixed> $base the application's `base:` */
    public function __construct(private readonly array $base = [], private readonly string $name = 'default')
    {
        // omnibridge/doctrine's metadata factory, which this package's suite does not install: Doctrine's own stands in
        // under its name, as the other services of omnibase do (HostExtension).
        if (!class_exists(HostConfiguration::METADATA_FACTORY)) {
            class_alias(ClassMetadataFactory::class, HostConfiguration::METADATA_FACTORY);
        }
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new BaseHostBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/host-'.$this->name;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    public function home(): Response
    {
        return new Response('home');
    }

    /** Who the visitors' firewall authenticated: the account's name, or nobody. */
    public function whoami(#[\Symfony\Component\Security\Http\Attribute\CurrentUser] ?\Symfony\Component\Security\Core\User\UserInterface $user = null): Response
    {
        return new Response($user?->getUserIdentifier() ?? 'anonymous');
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'host-secret',
            'test' => true,
            'router' => ['utf8' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'handle_all_throwables' => true,
        ]);
        $container->extension('security', [
            'firewalls' => [
                'dev' => ['pattern' => '^/_wdt', 'security' => false],
                'rescue' => ['pattern' => '^/rescue', 'context' => 'common'],
                'main' => ['pattern' => '^/', 'context' => 'common', 'lazy' => true],
            ],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            // What a site writes of its entities: where they are. Their attributes are omnibase's (Base\Mapping):
            // omnibridge/doctrine's DeclarationDriver reads them, around Doctrine's driver (DoctrineConfigurationPass).
            'orm' => ['mappings' => ['App' => ['is_bundle' => false, 'type' => 'attribute', 'dir' => __DIR__.'/Entity', 'prefix' => 'App\Entity']]],
        ]);
        $container->extension('base', $this->base);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller('kernel::home');
        $routes->add('private', '/private')->controller('kernel::home');
        $routes->add('sign_in', '/sign-in')->controller('kernel::home');
        $routes->add('whoami', '/whoami')->controller('kernel::whoami');
        $routes->add('rescue', '/rescue/private')->controller('kernel::home');
    }

    protected function build(ContainerBuilder $container): void
    {
        // omnibase's Doctrine mapping, as BaseBundle registers it, where omnibridge/doctrine is installed.
        if (class_exists(\Omnibridge\Doctrine\DeclarationDriver::class)) {
            $container->addCompilerPass(new DoctrineConfigurationPass());
        }
    }

    public function process(ContainerBuilder $container): void
    {
        foreach (['main', 'rescue', 'dev'] as $firewall) {
            $container->setParameter('host_test.firewall.'.$firewall, self::ids($container->getDefinition('security.firewall.map.config.'.$firewall)->getArguments()));
        }
        $calls = [];
        foreach ($container->getDefinition('doctrine.orm.default_configuration')->getMethodCalls() as $call) {
            if (\is_array($call) && \is_string($call[0] ?? null) && \is_array($call[1] ?? null)) {
                $calls[$call[0]] = self::ids($call[1]);
            }
        }
        $container->setParameter('host_test.doctrine', $calls);
        $container->setParameter('host_test.providers', array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'security.user.provider.concrete.'))));
    }

    /**
     * The strings and service ids of a definition's arguments.
     *
     * @param array<array-key, mixed> $arguments
     *
     * @return list<string>
     */
    private static function ids(array $arguments): array
    {
        $ids = [];
        array_walk_recursive($arguments, static function (mixed $argument) use (&$ids): void {
            if (\is_string($argument) || $argument instanceof \Stringable) {
                $ids[] = (string) $argument;
            }
        });

        return $ids;
    }
}
