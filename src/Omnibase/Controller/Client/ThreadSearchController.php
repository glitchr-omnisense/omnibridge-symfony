<?php

namespace Omnibridge\Symfony\Omnibase\Controller\Client;

use Base\Enum\ThreadState;
use Base\Form\FormProcessorInterface;
use Base\Form\FormProxyInterface;
use Base\Form\Type\ThreadSearchType;
use Base\Database\Repository\Repositories;
use Base\Repository\Threads;
use Base\Repository\ThreadTranslations;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ThreadSearchController extends AbstractController
{
    protected FormProxyInterface $formProxy;
    /** The translations searched and the threads of each class, by their interfaces (step R28), where it asked the entity manager. */
    protected ThreadTranslations $translations;
    protected Repositories $repositories;

    public function __construct(FormProxyInterface $formProxy, ThreadTranslations $translations, Repositories $repositories)
    {
        $this->formProxy = $formProxy;
        $this->translations = $translations;
        $this->repositories = $repositories;
    }

    #[Route(["en" => "/search", "fr" => "/rechercher"], name: "thread_search")]
    public function Main(Request $request)
    {
        $formProcessor = $this->formProxy->createProcessor("thread:search", ThreadSearchType::class, []);
        $formProcessor
            ->setData($this->formProxy->get("thread:searchbar")?->getData())
            ->onDefault(function (FormProcessorInterface $formProcessor) {
                return $this->render('client/thread/search.html.twig', [
                    "form" => $formProcessor->getForm()->createView(),
                    "form_data" => $formProcessor->getForm()->getData()
                ]);
            })
            ->onDefault(function (FormProcessorInterface $formProcessor) use ($request) {
                $threads = [];
                $data = $formProcessor->getData() ? clone $formProcessor->getData() : null;
                if ($data) {
                    $data->content = $data->content ?? $data->generic ?? "";
                    $data->title = $data->title ?? $data->generic ?? "";
                    $data->excerpt = $data->excerpt ?? $data->generic ?? "";

                    // The "%term%" wrapping that used to happen here now lives
                    // in searchTranslations, next to the LIKE it belongs to.
                    // $data keeps the raw terms, which is what the template
                    // needs to highlight them in the results.

                    $states = [ThreadState::PUBLISH];
                    if ($this->isGranted("ROLE_ADMIN")) {
                        $states = [];
                    }

                    $threads = array_map(fn($t) => $t->getTranslatable(), $this->searchTranslations($data, $states));

                    usort($threads, function ($a, $b) {
                        $aRepository = $this->repositories->of(get_class($a));
                        $bRepository = $this->repositories->of(get_class($b));
                        \assert($aRepository instanceof Threads && $bRepository instanceof Threads);

                        return $aRepository->getHierarchy() < $bRepository->getHierarchy() ? -1 : 1;
                    });
                }

                return $this->render('client/thread/search.html.twig', [
                    "form" => $formProcessor->getForm()->createView(),
                    "model" => $data,
                    "threads" => $threads,
                ]);
            })
            ->handleRequest($request);

        return $formProcessor->getResponse();
    }

    /**
     * Translations whose title, excerpt OR content matches the term, scoped
     * by thread state and parent: ThreadTranslations::search() (step R28),
     * the query written here before - "(a OR b OR c) AND scope", which the
     * finder DSL cannot say (it applies one separator to every criterion).
     */
    protected function searchTranslations(object $data, array $states): array
    {
        return $this->translations->search(
            ["title" => $data->title, "excerpt" => $data->excerpt, "content" => $data->content],
            $states,
            $data->parent_id ?: null,
        );
    }
}
