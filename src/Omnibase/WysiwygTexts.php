<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Contract\Texts;
use Base\Service\EditorEnhancerInterface;
use Base\View\Wysiwyg;

/**
 * The Texts contract under Symfony (plan "omnibase agnostique", C5): the
 * pipeline of omnibase's `|wysiwyg` and `|wysiwyg_toc` filters itself
 * (mentions, links, media, headings, Editor.js's renderer or the HTML of the
 * older editor), so a screen's text is the HTML the templates printed. Both are
 * Base\View\Wysiwyg (plan "omnibase agnostic", F4), which needs no Twig.
 */
final class WysiwygTexts implements Texts
{
    public function __construct(
        private readonly Wysiwyg $wysiwyg,
        private readonly EditorEnhancerInterface $editor,
    ) {
    }

    public function isEditor(?string $text): bool
    {
        return null !== $text && '' !== $text && $this->editor->supports($text);
    }

    public function render(?string $text, array $options = []): string
    {
        // As the filter: nothing for null, the editor's empty frame for ''.
        return null === $text ? '' : (string) $this->wysiwyg->render($text, $options);
    }

    public function toc(?string $text, ?int $maxLevel = null): array
    {
        return null === $text || '' === $text ? [] : array_values($this->wysiwyg->toc($text, $maxLevel));
    }
}
