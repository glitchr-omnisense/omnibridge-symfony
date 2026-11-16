<?php

namespace Base\DependencyInjection;

use Base\BaseBundle;
use Base\Database\Type\UtcDateTimeImmutableType;
use Base\Database\Type\UtcDateTimeType;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Base\DependencyInjection\Compiler\DiscoveryPass;

class BaseExtension extends AbstractBaseExtension implements PrependExtensionInterface
{
    /**
     * The tree of base:, for config:dump-reference and debug:config: Symfony looks for a class
     * named Configuration beside the extension, the core's is BaseConfiguration.
     */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new BaseConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        //
        // Load service declaration (includes services, controllers,..)
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 3) . '/config/omnibase'));
        // The core's services are its declaration (Base\Services; plan "omnibase agnostic", F5a and F5e slice 4): its
        // first group here, the others in their places among this bridge's own definitions (services/*.php).
        $loader->load('services-list.php');
        $loader->load('services.php');
        $loader->load('services-public.php');
        $loader->load('services-fix.php');
        $loader->load('services-decoration.php');

        // The container loads its services' classes through the autoloader, also with debug, where Symfony would
        // write include_once of their files (inline_class_loader). 1.x needed it for the core's former names (the
        // core 5.x aliased a moved class when the autoloader loaded it); kept: the App\ aliases are declared the same way.
        $container->setParameter('.container.dumper.inline_class_loader', false);

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new BaseConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        // The persistence (plan "omnibase agnostic", step F7d): base.persistence, required when omnibridge/doctrine and
        // omnibridge/eloquent are both installed; Eloquent's services in place of Doctrine's behind the contracts.
        // A site whose omnisense/omnibridge predates the choice (a 4.x site's lock) runs on Doctrine, as before.
        if (class_exists(\Omnibridge\Persistence\Persistences::class)) {
            Persistence::register($container, Persistence::choose($container, $config['persistence'] ?? null));
        }

        // Override and merge form_themes.. to add some features.. Where TwigBundle is registered: Twig is
        // optional in the core (plan "omnibase agnostic", F4), and so are its form themes.
        if ($container->hasParameter('twig.form.resources')) {
            $container->setParameter('twig.form.resources', array_merge(
                $config['twig']['form_themes'],
                $container->getParameter('twig.form.resources')
            ));
        }

        // The sign-in captcha's count in the pool the application names (base.guard.sign_in_pool: a pool shared by
        // its hosts), where it names one.
        if (null !== ($config['guard']['sign_in_pool'] ?? null) && $container->hasDefinition('Base\Security\SignInGuard')) {
            $container->getDefinition('Base\Security\SignInGuard')->setArgument('$cache', new Reference((string) $config['guard']['sign_in_pool']));
        }

        // The accounts as WebauthnBundle reads them (webauthn.user_repository, prepended), where the bundle is.
        if ($container->hasExtension('webauthn') || \in_array('Webauthn\Bundle\WebauthnBundle', $container->hasParameter('kernel.bundles') ? (array) $container->getParameter('kernel.bundles') : [], true)) {
            $container->register(HostConfiguration::PASSKEY_USERS, HostConfiguration::PASSKEY_USERS)
                ->setArguments([new Reference(HostConfiguration::USERS)])
                ->setPublic(true);
        }

        // What a service whose class is one of the core's interfaces is tagged with: the core's `autoconfigure` registry
        // (Base\Discovery), compiled here - Symfony reads it before any compiler pass.
        DiscoveryPass::autoconfigure($container, \Base\Discovery::registry('autoconfigure'));
    }

    public function prepend(ContainerBuilder $builder): void
    {
        // Where this package is, wherever Composer put it (vendor/omnisense/omnibase
        // since its rename from glitchr/omnibase, a path repository, a checkout):
        // what an application's configuration names instead of a vendor path -
        // wikidoc's roots ('%base.package_dir%/docs'), git's repositories.
        // Set before any extension loads, so every configuration can use it.
        $builder->setParameter('base.package_dir', BaseBundle::getBundleDir());

        $this->prependDoctrineTypes($builder);

        // What a site's configuration no longer names: omnibase's classes of Security, Doctrine, the framework and
        // WebauthnBundle, from the options of base: (HostConfiguration).
        HostConfiguration::prepend($builder, Persistence::hasDoctrine($builder));

        // The core's entities (Base\Entity), mapped by DoctrineBundle's auto_mapping from the bundle's
        // class until 5.0: the bundle is omnibridge/symfony's since the plan "omnibase agnostic"'s step F6,
        // and DoctrineBundle derives an attribute mapping's folder and prefix from the bundle class's own
        // folder and namespace. Said here, under the bundle's name, as auto_mapping wrote it before.
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['BaseBundle' => [
                'is_bundle' => false,
                'type' => 'attribute',
                'dir' => BaseBundle::getBundleDir().'/src/Entity',
                'prefix' => 'Base\\Entity',
                'alias' => 'BaseBundle',
            ]]]]);
        }

        // symfony/ux-twig-component is a suggestion since 4.0.
        if ($builder->hasExtension('twig_component')) {
            $builder->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'Base\\Twig\\Component\\' => [
                        'template_directory' => '@Base/components/',
                        'name_prefix' => 'base',
                    ],
                    'App\\Twig\\Component\\' => [
                        'template_directory' => '@App/components/',
                        'name_prefix' => 'app',
                    ],
                ],
            ]);
        }
    }

    /**
     * Registers the Enum/Set classes as doctrine.dbal.types.
     *
     * BaseBundle::boot() also calls Type::addType() for these, but that is far too
     * late: by the time boot() reaches it, entity metadata has already been built
     * and CACHED. Attributes that ask "what Doctrine type is this field?" during
     * loadClassMetadata therefore got null and silently declined - most visibly
     * OrderColumn, whose companion ordering column (e.g. User::rolesPositions)
     * never made it into the mapping. dev hid this because it rebuilds metadata
     * lazily, after boot; test and prod cached the amputated version for good, so
     * `doctrine:schema:update` created a table WITHOUT the column while the
     * running app still SELECTed it - "Unknown column 'u0_.rolesPositions'".
     *
     * Declaring them here instead makes DoctrineBundle's ConnectionFactory
     * register them while the connection is created, which necessarily precedes
     * any metadata load through that entity manager. boot()'s registration is
     * kept (it is hasType()-guarded, so it simply becomes a no-op) rather than
     * removed, because it also covers the built-in overrides.
     */
    private function prependDoctrineTypes(ContainerBuilder $builder): void
    {
        // Doctrine a suggestion (plan "omnibase agnostic", step B3): its types declared where DoctrineBundle and
        // omnibridge/doctrine are there.
        if (!$builder->hasExtension('doctrine') || !class_exists(UtcDateTimeType::class)) {
            return;
        }

        // Moments kept in UTC whatever zone PHP was put in for the visitor
        // (docs/20-architecture/time.md): there for every bundle and application.
        $types = [
            UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class,
            UtcDateTimeType::NAME => UtcDateTimeType::class,
        ];
        // The columns of a passkey, which the core describes (plan "omniauth", AU1).
        $types += \Omnibridge\Doctrine\Omnibase\Types::passkey();

        // The core's declaration of where its enumerations are and what they extend (Base\Discovery).
        foreach (\Base\Discovery::enumerations(BaseBundle::getProjectDir(), BaseBundle::getBundleDir()) as $name => $className) {
            $types[$name] = $className;
        }
        $builder->prependExtensionConfig('doctrine', ['dbal' => ['types' => $types]]);
    }
}
