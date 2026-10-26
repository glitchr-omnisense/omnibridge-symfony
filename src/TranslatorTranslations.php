<?php

namespace Omnibridge\Symfony;

use Omnibridge\Contract\Translations;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Omnibridge\Contract\Translations under Symfony: the application's
 * `translator` (whatever chain it is - omnibase's rewrites the texts of its
 * back office and reads "@domain.key" references before Symfony's ICU
 * catalogues), what Twig's |trans answers, the same strings; and whether a key
 * is there: the translator's own transExists() when it has one (omnibase's:
 * the catalogue of the locale, its fallbacks), else the catalogue of a
 * TranslatorBagInterface, "@domain.key" read as the key of that domain.
 *
 * Moved from omnisense/omnibase's Base\Bridge\Symfony\TranslatorTranslations
 * (plan "omnibase agnostique", A1); the former name is an alias of this class.
 */
final class TranslatorTranslations implements Translations
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function exists(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        if (method_exists($this->translator, 'transExists')) {
            return $this->translator->transExists($id, $domain, $locale);
        }
        if (!$this->translator instanceof TranslatorBagInterface) {
            return false;
        }

        if (str_starts_with($id, '@') && str_contains($id, '.')) {
            [$domain, $id] = explode('.', substr($id, 1), 2);
        }

        return $this->translator->getCatalogue($locale)->has($id, $domain ?? 'messages');
    }
}
