<?php

namespace Omnibridge\Symfony\Omnibase\Controller\UX;

use Base\Database\UnitOfWorks;
use Base\Field\Search\Answer;
use Base\Field\Search\ExchangeRate;
use Base\Field\Search\RemoteSearch;
use Base\Traits\BaseTrait;
use Doctrine\Persistence\ManagerRegistry;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The pickers' searches under Symfony: omnibase's Base\Field\Search\RemoteSearch (a select's records, an icon picker's
 * icons) and ExchangeRate (a money field's rate) decide (plan "omnibase agnostic", steps F5d and F5e slice 5b); the
 * profiler kept out of an ux_ route's answer and the repository built for an entity whose own is a plain Doctrine
 * one are Symfony's and Doctrine's.
 */
#[Route(priority: -1)]
class AutocompleteController extends AbstractController
{
    use BaseTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        protected readonly UnitOfWork $unitOfWork,
        protected readonly RemoteSearch $search,
        protected readonly ExchangeRate $exchangeRate,
        protected readonly ?Profiler $profiler = null,
        protected readonly ?ManagerRegistry $doctrine = null,
    ) {
    }

    /** A select's records (or an enumeration's values): Base\Field\Search\RemoteSearch::records(). */
    #[Route("/ux/autocomplete/{data}", name: "ux_autocomplete")]
    public function Main(Request $request, string $data): Response
    {
        $this->quiet();

        return self::respond($this->search->records($data, $request->getMethod(), $request->query->all(), $request->request->all(), $this->repository(...)));
    }

    /** A money field's exchange rate, stored only: Base\Field\Search\ExchangeRate::rate(). */
    #[Route("/ux/autocomplete/currency/{source}/{target}/{data}", name: "ux_autocomplete_forex")]
    public function Forex(string $source, string $target, string $data): Response
    {
        $this->quiet();
        $json = $this->exchangeRate->rate($source, $target, $data);

        return new JsonResponse($json->data, $json->status);
    }

    /** An icon adapter's icons: Base\Field\Search\RemoteSearch::icons(), on this host's icon provider. */
    #[Route("/ux/autocomplete/{provider}/{pageSize}/{data}", name: "ux_autocomplete_icons")]
    public function Icons(Request $request, string $provider, int $pageSize, string $data): Response
    {
        $this->quiet();
        $adapter = $this->getIconProvider()->getAdapter($provider);
        if (null === $adapter) {
            return self::respond(Answer::refused("Invalid request"));
        }

        return self::respond($this->search->icons($adapter, $pageSize, $data, $request->getMethod(), $request->query->all(), $request->request->all()));
    }

    /**
     * The repository a picker looks into: the entity's own when it is omnibase's (the finders are its parser's), else
     * one of omnibase's built for the class - a bundle's plain Doctrine repository answers no finder ("Undefined
     * method cacheByInstanceOf": the picker stayed on "Searching...").
     */
    protected function repository(string $class): ServiceEntityRepository
    {
        $repository = UnitOfWorks::repositories($this->unitOfWork)->of($class);
        if ($repository instanceof ServiceEntityRepository) {
            return $repository;
        }

        return new ServiceEntityRepository($this->doctrine ?? throw new \LogicException('The repository of '.$class.' is no omnibase repository, and no ManagerRegistry was given to build one.'), $class);
    }

    /** Symfony's profiler stays out of a ux_ route's answer. */
    private function quiet(): void
    {
        if (null !== $this->profiler && str_starts_with((string) $this->requestStack->getCurrentRequest()?->attributes->get("_route"), "ux_")) {
            $this->profiler->disable();
        }
    }

    private static function respond(Answer $answer): JsonResponse
    {
        return new JsonResponse($answer->body, $answer->status);
    }
}
