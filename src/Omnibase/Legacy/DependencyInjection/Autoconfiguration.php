<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection;

use Base\Attributes\AttributeInterface;
use Base\Cache\Abstract\AbstractLocalCacheInterface;
use Base\Database\Entity\EntityExtensionInterface;
use Base\Demo\DemoAccountProviderInterface;
use Base\EntityDispatcher\EventDispatcherInterface;
use Base\Service\Model\Currency\CurrencyApiInterface;
use Base\Service\Model\IconProvider\AbstractIconAdapter;
use Base\Service\Model\IconProvider\IconAdapterInterface;
use Base\Service\Model\Obfuscator\CompressionInterface;
use Base\Service\Model\Sharing\SharingAdapterInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The tags BaseExtension gave the core's interfaces until step F5e slice 4c, for omnisense/omnibase 4.x (a core without
 * Base\Discovery, whose `autoconfigure` registry DiscoveryPass::autoconfigure() compiles since). The Twig group's tag
 * renderers are config/omnibase/services/twig.php's, under the same condition. Removed once 4.x is no longer served.
 */
final class Autoconfiguration
{
    public static function register(ContainerBuilder $container): void
    {
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
}
