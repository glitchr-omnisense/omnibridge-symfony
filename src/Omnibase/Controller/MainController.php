<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Screen\Welcome;
use Omnibridge\Symfony\Omnibase\BaseBundle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A new site's root until it has a page of its own: omnibase's Base\Screen\Welcome (plan "omnibase agnostic", step F5e
 * slice 5b), told Symfony's version.
 */
#[Route(priority: -1)]
class MainController extends AbstractController
{
    #[Route("/", name: "app_welcome")]
    public function Index(): Response
    {
        return new Response(Welcome::html(Kernel::VERSION, BaseBundle::getProjectDir(), BaseBundle::VERSION), Welcome::STATUS);
    }
}
