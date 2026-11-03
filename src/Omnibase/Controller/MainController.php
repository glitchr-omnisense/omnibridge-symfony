<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Omnibridge\Symfony\Omnibase\BaseBundle;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;

use  Base\Service\SettingBag;
use function dirname;

#[Route(priority: -1)]
class MainController extends AbstractController
{
    protected SettingBag $settingBag;

    public function __construct(SettingBag $settingBag)
    {
        $this->settingBag = $settingBag;
    }

    /**
     * Controller example
     */
    
    #[Route("/", name: "app_welcome")]
    public function Index(): Response
    {
        $version = Kernel::VERSION;
        $projectDir = BaseBundle::getProjectDir();
        $docVersion = substr(Kernel::VERSION, 0, 3);
        $baseVersion = BaseBundle::VERSION;

        ob_start();
        include BaseBundle::getBundleDir() . '/src/Resources/views/welcome.html.php';
        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
