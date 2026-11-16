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
        return $this->treeBuilder ??= SchemaTree::build(Options::ROOT, Options::schema());
    }
}
