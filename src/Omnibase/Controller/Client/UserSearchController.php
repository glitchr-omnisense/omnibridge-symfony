<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Form\FormProxyInterface;
use Base\Form\Model\UserSearchModel;
use Base\Form\Type\UserSearchType;
use Base\Repository\Users;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;

class UserSearchController extends AbstractController
{
    protected FormProxyInterface $formProxy;
    /** The accounts by their interface (step R28), where it asked the entity manager for their repository. */
    protected Users $users;

    public function __construct(FormProxyInterface $formProxy, Users $users)
    {
        $this->formProxy = $formProxy;
        $this->users = $users;
    }

    #[Route("/search/user", name:"user_search")]
    public function Main(Request $request, ?FormInterface $formSearch = null)
    {
        $formSearch = $formSearch ?? $this->formProxy->get("user:search") ?? $this->createForm(UserSearchType::class, new UserSearchModel());
        $formSearch->handleRequest($request);

        $formattedData = null;
        if ($formSearch->isSubmitted() && $formSearch->isValid()) {
            $formattedData = clone $formSearch->getData();
        }

        // Members are looked for by their username, when the application's user has one: never by
        // their e-mail address, which this page would show to anyone (base User's identifier).
        $users = [];
        $term = trim((string) ($formattedData?->username ?? ""));
        if ($term !== "") {
            $users = array_values(array_filter($this->users->byUsernameContaining($term, 50), fn($u) => !$u->isBanned() && !$u->isGhost()));
        }

        return $this->render('client/user/search.html.twig', [
            "form" => $formSearch->createView(),
            "form_data" => $formattedData ?? new UserSearchModel(),
            "users" => $users
        ]);
    }
}
