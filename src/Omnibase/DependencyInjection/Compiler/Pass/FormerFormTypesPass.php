<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Base\Database\FormerNames;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Symfony Form under both names of a moved type (plan "omnibase agnostic", step F5b). A form type
 * moved to omnibridge/symfony is asked by its former name - `::class` of a former name is a plain
 * string, in a site's getParent(), buildForm() or getExtendedTypes() - and by its new one - the
 * types among themselves, and FormRegistry, which asks a type's extensions by `$type::class`, the
 * declared name. FormPass keys both maps by what was written: this pass, after it, gives the type
 * locator both names and files the type extensions under the declared name, or the types would be
 * built without their arguments and lose their extensions.
 */
final class FormerFormTypesPass implements CompilerPassInterface
{
    public static function getPriority(): int
    {
        return -64;
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('form.extension')) {
            return;
        }

        $extension = $container->getDefinition('form.extension');
        $moved = FormerNames::moved();
        $former = array_flip($moved);

        $types = $extension->getArgument(0);
        if ($types instanceof Reference) {
            $map = $container->getDefinition((string) $types)->getArgument(0);
            foreach ($map as $name => $reference) {
                $other = $moved[$name] ?? $former[$name] ?? null;
                if (null !== $other && !isset($map[$other])) {
                    $map[$other] = $reference;
                }
            }
            $extension->replaceArgument(0, ServiceLocatorTagPass::register($container, array_map(
                static fn ($service) => $service instanceof ServiceClosureArgument ? $service->getValues()[0] : $service,
                $map,
            )));
        }

        $extensions = [];
        foreach ($extension->getArgument(1) as $type => $iterator) {
            $declared = $moved[$type] ?? $type;
            $extensions[$declared] = isset($extensions[$declared])
                ? new IteratorArgument([...$extensions[$declared]->getValues(), ...$iterator->getValues()])
                : $iterator;
        }
        $extension->replaceArgument(1, $extensions);
    }
}
