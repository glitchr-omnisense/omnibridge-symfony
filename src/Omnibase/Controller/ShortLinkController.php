<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Attributes\Attribute\Route;
use Base\Request\ShortLinkRedirect;
use Omnibridge\Symfony\Omnibase\Response\AnswersOutcomes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * A short link (s.<host>) under Symfony: omnibase's Base\Request\ShortLinkRedirect decides where it leads (plan
 * "omnibase agnostic", step F5e slice 5b).
 */
class ShortLinkController extends AbstractController
{
    use AnswersOutcomes;

    public function __construct(private readonly ShortLinkRedirect $shortLinks)
    {
    }

    #[Route("/{slug}", name: "shlink_redirect", subdomain: "s", priority: 1)]
    #[Route("/{slug}/{_locale}", name: "shlink_redirectByLocale", subdomain: "s", priority: 1)]
    public function Main(string $slug): Response
    {
        return $this->answer($this->shortLinks->follow($slug));
    }
}
