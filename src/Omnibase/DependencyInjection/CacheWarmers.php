<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection;

/**
 * omnibase's cache warmers, as config/omnibase/services/core.php declares them: each service id, its arguments by
 * position (a service id, given only where it exists) and its bindings by name. A warmer built on a package the core
 * only suggests is declared only where that package is installed: PhpSpreadsheet's (phpoffice/phpspreadsheet, a
 * suggestion since omnisense/omnibase 5.0), whose class needs it - declared without it, it stopped cache:clear (its
 * optional warmers) with a ClassNotFoundError.
 */
final class CacheWarmers
{
    /** @return array<string, array<int|string, string>> */
    public static function declarations(): array
    {
        $warmers = class_exists(\PhpOffice\PhpSpreadsheet\Settings::class)
            ? ['Omnibridge\Symfony\Omnibase\Cache\Warmer\SpreadsheetCacheWarmer' => ['$cacheDir' => '%kernel.cache_dir%']]
            : [];

        return $warmers + [
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\IconCacheWarmer' => ['base.service.icon', '$cacheDir' => '%kernel.cache_dir%'],
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\LocalizerCacheWarmer' => ['localizer', '$cacheDir' => '%kernel.cache_dir%'],
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\ThemizerCacheWarmer' => ['themizer', '$cacheDir' => '%kernel.cache_dir%'],
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\AttributeCacheWarmer' => ['base.attribute_reader', '$cacheDir' => '%kernel.cache_dir%'],
            'Omnibridge\Symfony\Omnibase\Cache\Warmer\MetadataCacheWarmer' => ['base.database.metadata_manipulator', 'base.attribute_reader', '$cacheDir' => '%kernel.cache_dir%'],
        ];
    }
}
