<?php

namespace Omnibridge\Symfony\Omnibase\Controller\UX;

use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Base\Contract\Runtime;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;

use Base\Database\UnitOfWorks;
use Base\Field\Search\Answer;
use Base\Field\Search\RemoteSearch;
use Base\Service\MediaServiceInterface;
use Base\Service\Model\Autocomplete;
use Base\Service\ObfuscatorInterface;
use Base\Service\PaginatorInterface;
use Base\Service\TradingInterface;
use Base\Traits\BaseTrait;
use Doctrine\Persistence\ManagerRegistry;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(priority: -1)]
class AutocompleteController extends AbstractController
{
    use BaseTrait;

    /**
     * @var PaginatorInterface
     */
    protected PaginatorInterface $paginator;

    /**
     * @var ObfuscatorInterface
     */
    protected ObfuscatorInterface $obfuscator;
    protected UnitOfWork $unitOfWork;
    /**
     * @var ClassMetadataManipulator
     */
    protected ClassMetadataManipulator $classMetadataManipulator;
    /**
     * @var Autocomplete
     */
    protected Autocomplete $autocomplete;

    /**
     * @var TradingInterface
     */
    protected TradingInterface $tradingMarket;

    /**
     * @var Profiler|null
     */
    protected ?Profiler $profiler;

    /**
     * @var RequestStack
     */
    private RequestStack $requestStack;

    /**
     * @var ManagerRegistry|null
     */
    protected ?ManagerRegistry $doctrine;

    protected RemoteSearch $search;

    public function __construct(ObfuscatorInterface $obfuscator, RequestStack $requestStack, TradingInterface $tradingMarket, TranslatorInterface $translator, UnitOfWork $unitOfWork, PaginatorInterface $paginator, ClassMetadataManipulator $classMetadataManipulator, RemoteSearch $search, ?MediaServiceInterface $mediaService = null, ?Profiler $profiler = null, ?ManagerRegistry $doctrine = null)
    {
        $this->doctrine = $doctrine;
        $this->requestStack = $requestStack;
        $this->obfuscator = $obfuscator;
        $this->unitOfWork = $unitOfWork;
        $this->classMetadataManipulator = $classMetadataManipulator;
        $this->tradingMarket = $tradingMarket;
        $this->paginator = $paginator;
        $this->autocomplete = new Autocomplete($translator, $mediaService);
        $this->profiler = $profiler;
        $this->search = $search;
    }

    /** The search's answer as this host sends it. */
    private static function respond(Answer $answer): JsonResponse
    {
        return new JsonResponse($answer->body, $answer->status);
    }

    /** A select's records (or an enumeration's values): Base\Field\Search\RemoteSearch::records(). */
    #[Route("/ux/autocomplete/{data}", name:"ux_autocomplete")]
    public function Main(Request $request, string $data): Response
    {
        $this->quiet();

        return self::respond($this->search->records($data, $request->getMethod(), $request->query->all(), $request->request->all(), $this->repository(...)));
    }

    /** Symfony's profiler stays out of a ux_ route's answer. */
    private function quiet(): void
    {
        $isUX = str_starts_with((string) $this->requestStack->getCurrentRequest()?->attributes->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }
    }

    /**
     * The repository a picker looks into: the entity's own when it is
     * omnibase's (cacheByInstanceOf() and the other finders are its parser's);
     * else one of omnibase's built for the class. A bundle's plain Doctrine
     * repository - DoctrineBundle's ServiceEntityRepository, an
     * EntityRepository, which an entity inheriting another's mapping gets
     * when it names none (omnibase/classroom's levels and subjects, Taxon's) -
     * answers no such method: the search answered 500 ("Undefined method
     * cacheByInstanceOf"), and the picker stayed on "Searching...".
     */
    protected function repository(string $class): ServiceEntityRepository
    {
        $repository = UnitOfWorks::repositories($this->unitOfWork)->of($class);
        if ($repository instanceof ServiceEntityRepository) {
            return $repository;
        }

        if (null === $this->doctrine) {
            throw new \LogicException('The repository of '.$class.' is no omnibase repository, and no ManagerRegistry was given to build one.');
        }

        return new ServiceEntityRepository($this->doctrine, $class);
    }

    #[Route("/ux/autocomplete/currency/{source}/{target}/{data}", name:"ux_autocomplete_forex")]
    public function Forex(Request $request, string $source, string $target, string $data, ?Profiler $profiler = null): Response
    {
        $isUX = str_starts_with($this->requestStack->getCurrentRequest()->attributes->get("_route"), "ux_");
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $dict = $this->obfuscator->decode($data, ObfuscatorInterface::USE_SHORT);

        $token = $dict["token"] ?? null;
        $tokenName = $dict["token_name"] ?? null;
        if (!$tokenName || !$this->isCsrfTokenValid($tokenName, $token)) {
            return new JsonResponse("Invalid token. Please refresh the page and try again", 500);
        }

        // The stored rates only: a visitor's request never spends the
        // provider's quota, whether the application's Trading is live or not.
        $source = strtoupper($source);
        $target = strtoupper($target);

        $rate = $this->tradingMarket->getFallback($source, $target);
        if (!$rate) {
            return new JsonResponse("No rate stored for " . $source . "/" . $target, 404);
        }

        return new JsonResponse([
            "source" => $source,
            "target" => $target,
            "rate" => $rate->getValue(),
            "date" => $rate->getDate()->format(DATE_ATOM),
        ]);
    }


    /** An icon adapter's icons: Base\Field\Search\RemoteSearch::icons(), on this host's icon provider. */
    #[Route("/ux/autocomplete/{provider}/{pageSize}/{data}", name:"ux_autocomplete_icons")]
    public function Icons(Request $request, string $provider, int $pageSize, string $data, ?Profiler $profiler = null): Response
    {
        $this->quiet();

        $adapter = $this->getIconProvider()->getAdapter($provider);
        if (null === $adapter) {
            return self::respond(Answer::refused("Invalid request"));
        }

        return self::respond($this->search->icons($adapter, $pageSize, $data, $request->getMethod(), $request->query->all(), $request->request->all()));
    }
}
