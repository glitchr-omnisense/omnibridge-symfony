<?php

namespace Base\DependencyInjection;

use Base\Options;
use Base\Bundle\AbstractBaseConfiguration;
use Omnibridge\Symfony\Options\SchemaTree;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * The tree of base: compiled from the core's options, declared as data (Base\Options): the same declaration
 * omnibridge/laravel and a plain-PHP host validate theirs with.
 */
class BaseConfiguration extends AbstractBaseConfiguration
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        return $this->treeBuilder ??= SchemaTree::build(Options::ROOT, self::schema());
    }

    /**
     * The core's schema, with the options this host's configuration is prepended from (HostConfiguration) and the
     * sign-in captcha's pool where the core's schema does not declare them yet: one owner per option, the core's.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schema(): array
    {
        $schema = Options::schema();
        foreach (HostConfiguration::schema() as $name => $node) {
            $schema['security']['children'][$name] ??= $node;
        }
        $schema['guard']['children']['sign_in_pool'] ??= ['type' => 'scalar', 'default' => null, 'info' => 'The cache pool (a service) the sign-in captcha counts the failed sign-ins in (null: cache.app)'];

        return $schema;
    }
}
