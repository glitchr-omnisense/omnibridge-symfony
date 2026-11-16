<?php

namespace Base\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * AbstractPass for handling tagged services or entities implementing a specific interface.
 */
abstract class AbstractPass implements CompilerPassInterface
{
    abstract public function classFqcn(): string;

    abstract public function taggedServiceIds(): ?string;

    abstract public function serviceInterface(): ?string;

    abstract public function addMethod(): string;

    public function addArguments(string $className): array
    {
        return [];
    }

    public function process(ContainerBuilder $container): void
    {
        // Check if the primary service is defined
        if (!$container->has($this->classFqcn())) {
            return;
        }

        $definition = $container->findDefinition($this->classFqcn());

        // Handle tagged services
        if ($this->taggedServiceIds() !== null) {
            $taggedServices = $container->findTaggedServiceIds($this->taggedServiceIds());

            foreach ($taggedServices as $className => $tags) {
                $this->processService($container, $definition, $className);
            }
        }

        // Handle services implementing a specific interface
        if ($this->serviceInterface() !== null) {
            foreach ($container->getDefinitions() as $className => $serviceDefinition) {
                if (class_exists($className) && is_subclass_of($className, $this->serviceInterface())) {
                    $this->processService($container, $definition, $className);
                }
            }
        }
    }

    private function processService(ContainerBuilder $container, $definition, string $className): void
    {
        $reflectionClass = new \ReflectionClass($className);

        if ($this->serviceInterface() !== null && !$reflectionClass->implementsInterface($this->serviceInterface())) {
            throw new \InvalidArgumentException(sprintf(
                'Service "%s" must implement interface "%s".',
                $className,
                $this->serviceInterface()
            ));
        }

        $reference = new Reference($className);
        $args = $this->addArguments($className);
        array_unshift($args, $reference);

        $definition->addMethodCall($this->addMethod(), $args);
    }
}
