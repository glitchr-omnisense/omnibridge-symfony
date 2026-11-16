<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Base\DependencyInjection\HostConfiguration;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;
use Omnibridge\Symfony\Options\SchemaTree;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * `base:` in the test's application: the options HostConfiguration reads, prepended as omnibase's BaseExtension
 * prepends them, and a stand-in service under each id the prepended configuration names.
 */
final class HostExtension extends Extension implements PrependExtensionInterface, ConfigurationInterface
{
    public function getAlias(): string
    {
        return 'base';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        return SchemaTree::build('base', ['security' => ['type' => 'array', 'defaults' => true, 'children' => HostConfiguration::schema()]]);
    }

    /** @param array<array-key, mixed> $config */
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return $this;
    }

    public function prepend(ContainerBuilder $builder): void
    {
        HostConfiguration::prepend($builder, true);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $security = $this->processConfiguration($this, $configs)['security'] ?? [];
        $container->setParameter('base.security', \is_array($security) ? $security : []);

        $container->register(HostConfiguration::USER_CHECKER, UserChecker::class);
        $container->register(HostConfiguration::SIGN_IN, SignIn::class)->setArguments(['sign-in']);
        $container->register(HostConfiguration::RESCUE, SignIn::class)->setArguments(['rescue']);
        $container->register(HostConfiguration::ACCESS_TOKENS, AccessTokens::class);
        $container->register('Base\Controller\ErrorController', Errors::class)->setPublic(true)->addTag('controller.service_arguments');
        $container->register(HostConfiguration::NAMING_STRATEGY, DefaultNamingStrategy::class);
        $container->register(HostConfiguration::REPOSITORY_FACTORY, DefaultRepositoryFactory::class);
    }
}
