<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Service\LocalizerInterface;
use Omnibridge\Contract\Locales;

/**
 * Omnibridge\Contract\Locales under Symfony: omnibase's Localizer (the request's
 * locale, the site's default and fallbacks from base.yaml), its answers in a
 * list.
 */
final class LocalizerLocales implements Locales
{
    public function __construct(private readonly LocalizerInterface $localizer)
    {
    }

    public function getLocale(): string
    {
        return $this->localizer->getLocale();
    }

    public function getDefaultLocale(): string
    {
        return $this->localizer::getDefaultLocale() ?? $this->localizer->getLocale();
    }

    public function getAvailableLocales(): array
    {
        return array_values($this->localizer::getAvailableLocales());
    }
}
