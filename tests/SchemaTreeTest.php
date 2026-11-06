<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Options\InvalidOption;
use Omnibridge\Options\Processor as Options;
use Omnibridge\Symfony\Options\SchemaTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\Exception as ConfigException;
use Symfony\Component\Config\Definition\Processor;

/**
 * A schema of options as data compiled into Symfony's Config tree (Options\SchemaTree): the tree and
 * omnisense/omnibridge's Options\Processor, which Laravel and plain PHP validate with, give the same values and the
 * same refusals. omnisense/omnibase's own options are checked the same way in its harness (tests/Options).
 */
final class SchemaTreeTest extends TestCase
{
    private const SCHEMA = [
        'enabled' => ['type' => 'bool', 'default' => true, 'info' => 'On or off'],
        'mode' => ['type' => 'bool', 'default' => null],
        'size' => ['type' => 'int', 'default' => 3, 'min' => 0, 'max' => 10],
        'ratio' => ['type' => 'float', 'default' => null],
        'name' => ['type' => 'scalar', 'default' => 'x'],
        'level' => ['type' => 'enum', 'values' => [null, 'low', 'high'], 'default' => null],
        'extra' => ['type' => 'mixed', 'default' => []],
        'old' => ['type' => 'scalar', 'default' => null, 'deprecated' => ['package' => 'acme/options', 'since' => '1.0', 'message' => 'The "%node%" option at "%path%" is gone.']],
        'section' => ['type' => 'array', 'defaults' => true, 'children' => [
            'a' => ['type' => 'scalar', 'default' => 1],
            'b' => ['type' => 'array', 'prototype' => ['type' => 'scalar'], 'default' => ['b'], 'deep_merging' => false],
        ]],
        'items' => ['type' => 'array', 'prototype' => ['type' => 'array', 'defaults' => true, 'children' => ['id' => ['type' => 'scalar'], 'on' => ['type' => 'bool', 'default' => false]]]],
        'named' => ['type' => 'array', 'key' => 'name', 'prototype' => ['type' => 'array', 'children' => ['icon' => ['type' => 'scalar', 'default' => null]]]],
        'firewalls' => ['type' => 'array', 'default_children' => true, 'prototype' => ['type' => 'scalar', 'default' => 'main']],
    ];

    /** @return iterable<string, array{list<array<array-key, mixed>|null>}> */
    public static function configurations(): iterable
    {
        yield 'nothing' => [[]];
        yield 'all given' => [[['enabled' => false, 'mode' => null, 'size' => 10, 'ratio' => 2, 'name' => 5, 'level' => 'low', 'extra' => ['a' => [1]], 'old' => 'x',
            'section' => ['a' => 'z', 'b' => ['c']], 'items' => [['id' => 1], ['id' => 2, 'on' => true]], 'named' => [['name' => 'n', 'icon' => 'i']], 'firewalls' => ['api']]]];
        yield 'merged' => [[['section' => ['b' => ['c']], 'items' => [['id' => 1]], 'named' => ['n' => ['icon' => 'i']]], ['section' => ['b' => ['d']], 'items' => [['id' => 2]], 'named' => ['m' => []]]]];
        yield 'null and dashes' => [[['enabled' => null, 'section' => null, 'fire-walls' => null]]];
        yield 'unknown' => [[['nam' => 1]]];
        yield 'wrong bool' => [[['enabled' => 'yes']]];
        yield 'wrong nullable bool' => [[['mode' => 1]]];
        yield 'too big' => [[['size' => 11]]];
        yield 'wrong enum' => [[['level' => 'mid']]];
        yield 'wrong array' => [[['section' => 'x']]];
        yield 'key missing' => [[['named' => [['icon' => 'i']]]]];
    }

    /** @param list<array<array-key, mixed>|null> $configs */
    #[DataProvider('configurations')]
    public function testTheTreeAndTheProcessorAnswerAlike(array $configs): void
    {
        if (!class_exists(Options::class)) {
            self::markTestSkipped('Requires omnisense/omnibridge with Options\Processor.');
        }
        $tree = SchemaTree::build('acme', self::SCHEMA)->buildTree();

        self::assertSame(
            self::outcome(static fn (): array => (new Processor())->process($tree, $configs)),
            self::outcome(static fn (): array => Options::process(self::SCHEMA, 'acme', $configs)),
        );
    }

    /** @return array<string, mixed> */
    private static function outcome(\Closure $process): array
    {
        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            $result = ['config' => $process()];
        } catch (InvalidOption|ConfigException $e) {
            $result = ['refused' => $e->getMessage()];
        } finally {
            restore_error_handler();
        }

        return $result + ['deprecations' => $deprecations];
    }
}
