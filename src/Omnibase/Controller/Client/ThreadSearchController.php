<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Database\Repository\Repositories;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\ThreadSearchType;
use Base\Repository\ThreadTranslations;
use Base\Thread\ThreadSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The threads' search under Symfony: omnibase's Base\Thread\ThreadSearch decides (plan "omnibase agnostic", step F5e
 * slice 5b) - the terms, the states an account sees, the order -; the form (prefilled by the search bar's) is Symfony's.
 */
class ThreadSearchController extends AbstractController
{
    /** The translations and the repositories stay for an application's controller that extends this one (lesringostars' does). */
    public function __construct(
        protected FormProxyInterface $formProxy,
        protected ThreadTranslations $translations,
        protected Repositories $repositories,
        protected ?ThreadSearch $search = null,
    ) {
    }

    #[Route(["en" => "/search", "fr" => "/rechercher"], name: "thread_search")]
    public function Main(Request $request)
    {
        return $this->formProxy->createProcessor("thread:search", ThreadSearchType::class, [])
            ->setData($this->formProxy->get("thread:searchbar")?->getData())
            ->onDefault(fn (FormProcessorInterface $formProcessor) => $this->render(ThreadSearch::TEMPLATE, [
                "form" => $formProcessor->getForm()->createView(),
                "form_data" => $formProcessor->getForm()->getData(),
            ]))
            ->onDefault(function (FormProcessorInterface $formProcessor) {
                [$model, $threads] = ($this->search ?? throw new \LogicException('No Base\\Thread\\ThreadSearch given.'))->find($formProcessor->getData());

                return $this->render(ThreadSearch::TEMPLATE, ["form" => $formProcessor->getForm()->createView(), "model" => $model, "threads" => $threads]);
            })
            ->handleRequest($request)
            ->getResponse();
    }
}
