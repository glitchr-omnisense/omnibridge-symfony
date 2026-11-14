<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Omnibase\DependencyInjection\CacheWarmers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * omnibase's services declared only with the packages they are built on, where omnisense/omnibase only suggests
 * them since 5.0: PhpSpreadsheet's cache warmer only where phpoffice/phpspreadsheet is installed - declared without
 * it, it stopped cache:clear (its optional warmers) with a ClassNotFoundError. This package does not install it, in
 * its suite as in `docker compose run --rm bare` (group without-omniauth): there, the warmer is not declared, the
 * others are, with their arguments as before.
 */
#[Group('without-omniauth')]
final class OptionalPackagesTest extends TestCase
{
    public function testTheSpreadsheetWarmerOnlyWithPhpSpreadsheet(): void
    {
        $installed = class_exists(\PhpOffice\PhpSpreadsheet\Settings::class);
        self::assertFalse($installed, 'this package\'s suite runs without phpoffice/phpspreadsheet');

        $warmers = CacheWarmers::declarations();
        self::assertArrayNotHasKey('Omnibridge\Symfony\Omnibase\Cache\Warmer\SpreadsheetCacheWarmer', $warmers);
        self::assertSame([
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\IconCacheWarmer',
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\LocalizerCacheWarmer',
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\ThemizerCacheWarmer',
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\AttributeCacheWarmer',
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\MetadataCacheWarmer',
        ], array_keys($warmers));
        self::assertSame(['base.database.metadata_manipulator', 'base.attribute_reader', '$cacheDir' => '%kernel.cache_dir%'], $warmers['Omnibridge\Symfony\Omnibase\Cache\Warmer\MetadataCacheWarmer']);
    }
}
