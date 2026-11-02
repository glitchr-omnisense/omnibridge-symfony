<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Contract\Pictures;
use Base\View\Commons;

/**
 * The Pictures contract under Symfony (plan "omnibase agnostique", C5): the
 * answers of omnibase's `|picture` filter itself - a stored image's thumbnail
 * by MediaService, an address or a public path as it is, an asset of the
 * build - so a screen's images are those the templates printed. Both are
 * Base\View\Commons (plan "omnibase agnostic", F4), which needs no Twig.
 */
final class MediaPictures implements Pictures
{
    public function __construct(private readonly Commons $commons)
    {
    }

    public function picture(?string $image, ?int $width = null, ?int $height = null): ?string
    {
        return $this->commons->picture($image, $width, $height);
    }
}
