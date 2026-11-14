<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Auth\Account\MemberSearch;
use Omnibridge\Symfony\Omnibase\Form\FormProxyInterface;
use Base\Form\Model\UserSearchModel;
use Omnibridge\Symfony\Omnibase\Form\Type\UserSearchType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The members' search under Symfony: omnibase's Base\Auth\Account\MemberSearch decides who is found (plan "omnibase
 * agnostic", step F5e slice 5b); the form is Symfony's.
 */
class UserSearchController extends AbstractController
{
    public function __construct(protected FormProxyInterface $formProxy, protected MemberSearch $search)
    {
    }

    #[Route("/search/user", name: "user_search")]
    public function Main(Request $request, ?FormInterface $formSearch = null)
    {
        $formSearch = $formSearch ?? $this->formProxy->get("user:search") ?? $this->createForm(UserSearchType::class, new UserSearchModel());
        $formSearch->handleRequest($request);
        $data = $formSearch->isSubmitted() && $formSearch->isValid() ? clone $formSearch->getData() : null;

        return $this->render(MemberSearch::TEMPLATE, [
            "form" => $formSearch->createView(),
            "form_data" => $data ?? new UserSearchModel(),
            "users" => $this->search->find($data?->username ?? null),
        ]);
    }
}
