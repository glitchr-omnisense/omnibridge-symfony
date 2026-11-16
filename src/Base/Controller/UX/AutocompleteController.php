<?php

namespace Base\Controller\UX;

use Base\Database\UnitOfWorks;
use Base\Field\Search\Answer;
use Base\Field\Search\ExchangeRate;
use Base\Field\Search\RemoteSearch;
use Base\Traits\BaseTrait;
use Base\Database\Repository\ServiceEntityRepository;
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
        // Null without symfony/http-client (the core suggests it): no rate is asked, the forex route answers 404.
        protected readonly ?ExchangeRate $exchangeRate,
        protected readonly ?Profiler $profiler = null,
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
        if (null === $this->exchangeRate) {
            return new JsonResponse(['error' => 'No exchange rates here: symfony/http-client is not installed.'], Response::HTTP_NOT_FOUND);
        }
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
     * The repository a picker looks into: the entity's own, or omnibase's parent class built for an entity that has
     * none - either answers the finder language, on any persistence.
     *
     * @return ServiceEntityRepository<object>
     */
    protected function repository(string $class): ServiceEntityRepository
    {
        $repository = UnitOfWorks::repositories($this->unitOfWork)->of($class);
        \assert($repository instanceof ServiceEntityRepository);

        return $repository;
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
