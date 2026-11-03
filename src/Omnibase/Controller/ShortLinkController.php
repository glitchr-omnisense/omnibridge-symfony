<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Repository\Layout\ShortLinkRepository;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Base\Attributes\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class ShortLinkController extends AbstractController
{
    /**
     * @var ShortLinkRepository
     */
    protected $shlinkRepository;

    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    public function __construct(AdvancedRouterInterface $router, ShortLinkRepository $shlinkRepository)
    {
        $this->router = $router;
        $this->shlinkRepository = $shlinkRepository;
    }

    #[Route("/{slug}", name: "shlink_redirect", subdomain: "s", priority: 1)]
    #[Route("/{slug}/{_locale}", name: "shlink_redirectByLocale", subdomain: "s", priority: 1)]
    public function Main(string $slug): Response
    {
        /**
         * @var ShortLink $this
         */
        $shlink = $this->shlinkRepository->findOneBySlug($slug);
        if ($shlink === null) {
            throw $this->createNotFoundException("Page requested doesn't exist.");
        }

        return $this->redirect($this->router->format($shlink->getUrl()));
    }
}
