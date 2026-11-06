<?php

namespace Omnibridge\Symfony\Options;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\NumericNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * A schema of options declared as data (omnisense/omnibridge's Options\Processor node format: type, info,
 * default, deprecated, min/max, values; an array's children or prototype) compiled into Symfony's Config tree:
 * what an extension's Configuration returns, so that config:dump-reference, debug:config and the processed
 * configuration come from the same declaration Laravel and plain PHP validate theirs with. Each node is built with
 * the builder calls a hand-written tree would make, so the tree is the same.
 */
final class SchemaTree
{
    /** The builder's node type of each schema type. */
    private const TYPES = ['bool' => 'boolean', 'int' => 'integer', 'float' => 'float', 'scalar' => 'scalar', 'enum' => 'enum', 'mixed' => 'variable', 'array' => 'array'];

    /** @param array<string, array<string, mixed>> $schema the root's children */
    public static function build(string $root, array $schema, ?TreeBuilder $builder = null): TreeBuilder
    {
        $builder ??= new TreeBuilder($root);
        $node = $builder->getRootNode();
        \assert($node instanceof ArrayNodeDefinition);
        self::children($node, $schema);

        return $builder;
    }

    /** @param array<string, array<string, mixed>> $children */
    private static function children(ArrayNodeDefinition $parent, array $children): void
    {
        $builder = $parent->children();
        foreach ($children as $name => $child) {
            self::configure($builder->node($name, self::type($child, $name)), $child);
        }
    }

    /** @param array<string, mixed> $node */
    private static function configure(NodeDefinition $definition, array $node): void
    {
        if (isset($node['info'])) {
            $definition->info($node['info']);
        }
        if ('enum' === $node['type']) {
            $definition->values($node['values']); // @phpstan-ignore method.notFound (an EnumNodeDefinition)
        }
        if ($definition instanceof NumericNodeDefinition) {
            if (isset($node['min'])) {
                $definition->min($node['min']);
            }
            if (isset($node['max'])) {
                $definition->max($node['max']);
            }
        }
        if ($definition instanceof ArrayNodeDefinition) {
            if (isset($node['prototype'])) {
                self::configure($definition->prototype(self::TYPES[$node['prototype']['type']]), $node['prototype']);
                if (isset($node['key'])) {
                    $definition->useAttributeAsKey($node['key']);
                }
                if (true === ($node['default_children'] ?? false)) {
                    $definition->addDefaultChildrenIfNoneSet();
                }
            } else {
                self::children($definition, $node['children'] ?? []);
            }
            if (true === ($node['defaults'] ?? false)) {
                $definition->addDefaultsIfNotSet();
            }
            if (false === ($node['deep_merging'] ?? true)) {
                $definition->performNoDeepMerging();
            }
        }
        if (\array_key_exists('default', $node)) {
            $definition->defaultValue($node['default']);
        }
        if (isset($node['deprecated'])) {
            $definition->setDeprecated($node['deprecated']['package'], $node['deprecated']['since'], $node['deprecated']['message']);
        }
    }

    /** @param array<string, mixed> $node */
    private static function type(array $node, string $name): string
    {
        return self::TYPES[$node['type'] ?? ''] ?? throw new \InvalidArgumentException(\sprintf('Unknown option type "%s" for "%s".', $node['type'] ?? '', $name));
    }
}
