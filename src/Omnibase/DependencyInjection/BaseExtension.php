<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection;

use Base\Attributes\AttributeInterface;
use Omnibridge\Symfony\Omnibase\BaseBundle;
use Omnibridge\Doctrine\Omnibase\Database\Type\UtcDateTimeImmutableType;
use Omnibridge\Doctrine\Omnibase\Database\Type\UtcDateTimeType;
use Doctrine\DBAL\Types\Type;
use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Demo\DemoAccountProviderInterface;
use Base\EntityDispatcher\EventDispatcherInterface;
use Base\Service\Model\Currency\CurrencyApiInterface;
use Base\Service\Model\IconProvider\AbstractIconAdapter;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Service\Model\Obfuscator\CompressionInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Workflow\WorkflowInterface;

use Omnibridge\Symfony\Omnibase\Bundle\AbstractBaseExtension;
use Base\Service\Model\Sharing\SharingAdapterInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

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
        // write include_once of their files (inline_class_loader): a class moved to omnibridge/doctrine (plan
        // "omnibase agnostic", step F3) loaded by its file was never aliased by its former name, which the core's
        // autoloader declares when the class is loaded (src/Resources/former-names.php) - and PHP checks a type or
        // an instanceof written with the former name without autoloading it, so they failed on that class.
        $container->setParameter('.container.dumper.inline_class_loader', false);

        // Configuration file: ./config/package/base.yaml
        $processor = new Processor();
        $configuration = new BaseConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);

        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->getRootNode()->getNode()->getName());

        // Override and merge form_themes.. to add some features.. Where TwigBundle is registered: Twig is
        // optional in the core (plan "omnibase agnostic", F4), and so are its form themes.
        if ($container->hasParameter('twig.form.resources')) {
            $container->setParameter('twig.form.resources', array_merge(
                $config['twig']['form_themes'],
                $container->getParameter('twig.form.resources')
            ));
        }

        $container->registerForAutoconfiguration(AbstractIconAdapter::class)->addTag('base.service.icon');
        $container->registerForAutoconfiguration(EntityExtensionInterface::class)->addTag('base.entity_extension');
        $container->registerForAutoconfiguration(AttributeInterface::class)->addTag('base.attribute');
        $container->registerForAutoconfiguration(IconAdapterInterface::class)->addTag('base.icon_provider');
        $container->registerForAutoconfiguration(SharingAdapterInterface::class)->addTag('base.service.sharing');
        $container->registerForAutoconfiguration(AbstractLocalCacheInterface::class)->addTag('base.simple_cache');
        $container->registerForAutoconfiguration(CurrencyApiInterface::class)->addTag('currency.api');
        $container->registerForAutoconfiguration(CompressionInterface::class)->addTag('obfuscator.compressor');
        $container->registerForAutoconfiguration(WorkflowInterface::class)->addTag('workflow');
        // The demonstration accounts a bundle or the application declares (docs/20-architecture/demo.md).
        $container->registerForAutoconfiguration(DemoAccountProviderInterface::class)->addTag('base.demo_account_provider');
        // omnibase's repositories are doctrine/orm's EntityRepository built from the ManagerRegistry
        // (plan "omnibase agnostique", C3b), no longer DoctrineBundle's ServiceEntityRepository, whose
        // interface DoctrineBundle tags: an application's repository extending them (App\Repository\*)
        // is tagged here, and DoctrineBundle's repository factory finds it in the container as before.
        $container->registerForAutoconfiguration(\Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository::class)->addTag('doctrine.repository_service');

        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "preUpdate"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postUpdate"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "prePersist"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postPersist"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "preRemove"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postRemove"]);
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)->addTag('doctrine.event_listener', ["event" => "postFlush"])
            // Flushed after the flush that raised their events (EntityDispatcherFlushSubscriber).
            ->addTag('base.entity_dispatcher')
            ->addTag('kernel.reset', ['method' => 'reset']);
        
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
        // Moments kept in UTC whatever zone PHP was put in for the visitor
        // (docs/20-architecture/time.md): there for every bundle and application.
        $types = [
            UtcDateTimeImmutableType::NAME => UtcDateTimeImmutableType::class,
            UtcDateTimeType::NAME => UtcDateTimeType::class,
        ];
        // The columns of a passkey, which the core describes (plan "omniauth", AU1).
        $types += \Omnibridge\Doctrine\Omnibase\Types::passkey();

        $classList = array_merge(
            BaseBundle::getAllClasses(BaseBundle::getBundleDir() . "/src/Enum"),
            BaseBundle::getAllClasses(BaseBundle::getProjectDir() . "/src/Enum")
        );

        foreach ($classList as $className) {

            // An enumeration (no Doctrine type since step F2a of the plan "omnibase agnostic"): listed
            // here as before, EnumerationTypesPass hands it to EnumerationsMiddleware, which registers an
            // EnumTypeAdapter of it when the connection is made.
            if (!is_subclass_of($className, \Base\Database\Type\EnumType::class) || (new \ReflectionClass($className))->isAbstract()) {
                continue;
            }

            $types[$className::getStaticName()] = $className;
        }

        $builder->prependExtensionConfig('doctrine', ['dbal' => ['types' => $types]]);
    }
}
