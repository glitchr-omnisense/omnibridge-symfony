<?php

namespace Base\DependencyInjection\Compiler\Pass;

use Base\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Model\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;

class WorkflowPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'workflow';
    }

    public function classFqcn(): string
    {
        return Registry::class;
    }

    public function addMethod(): string
    {
        return 'addWorkflow';
    }

    public function process(ContainerBuilder $container): void
    {
        // always first check if the primary service is defined
        if (!$container->has($this->classFqcn())) {
            return;
        }

        $registryDefinition = $container->findDefinition($this->classFqcn());
        $taggedServices = $container->findTaggedServiceIds($this->taggedServiceIds());
        foreach ($taggedServices as $className => $tags) {
            if (!is_instanceof($className, WorkflowInterface::class)) {
                continue;
            }

            $reference = new Reference($className);
            // $container->setAlias($className::getWorkflowName(), $className); ?

            $supportedClassNames = $className::supports();
            $supportStrategy = $className::supportStrategy();
            if ($supportedClassNames) {
                foreach ($supportedClassNames as $supportedClassName) {
                    if (!class_exists($supportedClassName)) {
                        throw new \LogicException('Non-existing class "' . $supportedClassName . '" requesting support for "' . $className . '" workflow.');
                    }

                    $strategyDefinition = new Definition(InstanceOfSupportStrategy::class, [$supportedClassName]);
                    $strategyDefinition->setPublic(false);
                    $registryDefinition->addMethodCall('addWorkflow', [$reference, $strategyDefinition]);
                }
            } elseif (isset($supportStrategy)) {
                $registryDefinition->addMethodCall('addWorkflow', [$reference, new Reference($supportStrategy)]);
            }
        }
    }
}
