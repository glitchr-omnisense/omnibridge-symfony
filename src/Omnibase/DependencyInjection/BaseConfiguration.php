<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection;

use Base\Options;
use Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\BaseConfiguration as Legacy;
use Omnibridge\Symfony\Options\SchemaTree;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

if (!class_exists(Options::class)) {
    // omnisense/omnibase without Base\Options (4.x): the tree is still this bridge's former code (Legacy\, removed once
    // 4.x is no longer served).
    class BaseConfiguration extends Legacy
    {
    }
} else {
    /**
     * The tree of base: compiled from the core's options, declared as data (Base\Options; plan "omnibase agnostic",
     * step F5e slice 4b): the same declaration omnibridge/laravel and a plain-PHP host validate theirs with.
     */
    class BaseConfiguration extends Legacy
    {
        public function getConfigTreeBuilder(): TreeBuilder
        {
            return $this->treeBuilder ??= SchemaTree::build(Options::ROOT, Options::schema());
        }
    }
}
