<?php

namespace Base\Bundle;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

abstract class AbstractBaseConfiguration implements ConfigurationInterface
{
    protected ?TreeBuilder $treeBuilder = null;

    /**
     * @param ?string $treename
     * @return TreeBuilder
     */
    public function getTreeBuilder(?string $treeName = null): TreeBuilder
    {
        if($this->treeBuilder !== null) return $this->treeBuilder;
        
        $treeName ??= camel2snake(basename_namespace(str_rstrip(lcfirst(static::class), "Configuration")));
        $this->treeBuilder = new TreeBuilder($treeName);

        return $this->treeBuilder;
    }
}
