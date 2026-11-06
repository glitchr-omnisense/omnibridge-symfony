<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Attributes\Attribute\Iconize;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\SecurityLoginType;
use Base\Auth\Account\RescuePage;
use Twig\Environment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The admin-firewall login ("rescue") entry point under Symfony: omnibase's RescuePage decides (plan "omnibase
 * agnostic", step F5e slice 5) - where a signed-in account goes, the logo, the page to go to once signed in -;
 * the form, Twig's loader (the admin extension's page, else the self-contained one bundled with the core) and
 * the back office's address (its `admin` route, "/" without one) are Symfony's.
 */
class RescueController extends AbstractController
{
    public function __construct(
        protected RouterInterface $router,
        protected Environment $twig,
        protected FormProxyInterface $formProxy,
        protected RescuePage $rescue,
    ) {
    }

    #[Route("/rescue", name: "security_rescue")]
    #[Iconize(["fa-solid fa-lock", "fa-solid fa-unlock"])]
    public function LoginRescue(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($outcome = $this->rescue->signedIn($this->adminUrl())) {
            return $this->redirect((string) $outcome->url);
        }

        return $this->formProxy
            ->createProcessor("form:login:rescue", SecurityLoginType::class, ["identifier" => $authenticationUtils->getLastUsername()])
            ->onDefault(fn (FormProcessorInterface $formProcessor) => $this->render($this->twig->getLoader()->exists(RescuePage::ADMIN_TEMPLATE) ? RescuePage::ADMIN_TEMPLATE : RescuePage::TEMPLATE, [
                'last_username' => $authenticationUtils->getLastUsername(),
                'translation_domain' => 'forms',
                'target_path' => $this->rescue->target($this->adminUrl()),
                'identifier_label' => '@forms.login.identifier',
                'password_label' => '@forms.login.password',
                'logo' => $this->rescue->logo(),
                'error' => $authenticationUtils->getLastAuthenticationError(),
                'identifier' => $authenticationUtils->getLastUsername(),
                'form' => $formProcessor->getForm()->createView(),
            ]))
            ->handleRequest($request)
            ->getResponse();
    }

    #[Route("/rescue-request", name: "admin_rescue", priority: -1)]
    public function index(): Response
    {
        return $this->redirectToRoute("security_rescue");
    }

    /** The back office's home, or "/" where no admin dashboard is mounted. */
    private function adminUrl(): string
    {
        try {
            return $this->router->generate(RescuePage::ADMIN_ROUTE);
        } catch (\Throwable) {
            return '/';
        }
    }
}
