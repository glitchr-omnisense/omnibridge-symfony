<?php

namespace Omnibridge\Symfony\Tests;

use PHPUnit\Framework\TestCase;

/**
 * One owner per class of Base\: a class this bridge declares under Base\ (src/Base) is not declared by the core
 * (omnisense/omnibase's src), nor the other way round. Against the core installed in vendor/, or the checkout
 * OMNIBASE_CORE_DIR names; skipped where there is neither.
 */
final class SingleOwnerTest extends TestCase
{
    public function testNoClassOfBaseIsDeclaredByThisBridgeAndByTheCore(): void
    {
        $core = getenv('OMNIBASE_CORE_DIR') ?: \dirname(__DIR__).'/vendor/omnisense/omnibase';
        if (!is_dir($core.'/src')) {
            self::markTestSkipped('No omnisense/omnibase to compare with (vendor/omnisense/omnibase or OMNIBASE_CORE_DIR).');
        }
        $own = self::classes(\dirname(__DIR__).'/src/Base');
        self::assertNotEmpty($own);
        self::assertSame([], array_values(array_intersect($own, self::classes($core.'/src'))), 'declared twice under Base\\');
    }

    public function testNothingOfTheBridgeIsLeftUnderItsFormerNamespaceButPlumbing(): void
    {
        // What a site or a bundle could name lives under Base\; src/Omnibase keeps the adapters of the core's contracts.
        foreach (['Bundle', 'Console', 'Controller', 'Field', 'Routing', 'Security', 'Subscriber', 'Legacy'] as $folder) {
            self::assertDirectoryDoesNotExist(\dirname(__DIR__).'/src/Omnibase/'.$folder);
        }
    }

    /** @return list<string> the files of a folder, relative, sorted */
    private static function classes(string $folder): array
    {
        $classes = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && 'php' === $file->getExtension()) {
                $classes[] = substr($file->getPathname(), \strlen($folder) + 1);
            }
        }
        sort($classes);

        return $classes;
    }
}
