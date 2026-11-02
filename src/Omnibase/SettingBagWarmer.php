<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Service\SettingBag;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * The settings' snapshot compiled at cache:warmup (plan "omnibase agnostique",
 * C9): SettingBag's warmer, HttpKernel's side of it, so that SettingBag itself
 * stands on psr/cache and Doctrine alone. Optional: the snapshot compiles on its
 * first read anyway; an unreachable database warms nothing (SettingBag::warmUp()).
 */
final class SettingBagWarmer implements CacheWarmerInterface
{
    public function __construct(private readonly SettingBag $settingBag)
    {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->settingBag->warmUp($cacheDir, $buildDir);
    }
}
