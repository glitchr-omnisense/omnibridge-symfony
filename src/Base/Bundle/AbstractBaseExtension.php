<?php

namespace Base\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

abstract class AbstractBaseExtension extends Extension
{
    /**
     * @param ContainerBuilder $container
     * @param array $config
     * @param $globalKey
     * @return void
     */
    public function setConfiguration(ContainerBuilder $container, array $config, $globalKey = '')
    {
        foreach ($config as $key => $value) {
            if (!empty($globalKey)) {
                $key = $globalKey . '.' . $key;
            }

            if (is_array($value)) {

                // Also expose the array itself, not just its flattened leaves:
                // a list-valued option (e.g. `base.time_machine.excludes`) would
                // otherwise only ever exist as `<key>.0`, `<key>.1`, .. and be
                // unreachable as a single parameter.
                $container->setParameter($key, $value);
                $this->setConfiguration($container, $value, $key);
            } else {
                $container->setParameter($key, $value);
            }
        }
    }

    /**
     * @return string
     */
    public function getBaseNamespace()
    {
        return implode("\\", array_slice(explode("\\", static::class), 0, -2));
    }

    public function setConfigurationAliases(ContainerBuilder $container)
    {
        $baseNamespace = $this->getBaseNamespace();
        $baseDefinitions = array_transforms(
            function($k, $v): array {

                $k = explode("\\", $k);
                array_swap($k, 1, 2);
                return [implode("\\", $k), $v];
            }, 
            
            array_starts_with($container->getDefinitions(), $baseNamespace)
        );

        foreach($baseDefinitions as $className => $definition) {            
            $container->setDefinition($className, $definition);
        }
    }
}
