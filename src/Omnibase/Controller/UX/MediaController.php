<?php

namespace Omnibridge\Symfony\Omnibase\Controller\UX;

use Base\Attributes\Attribute\IsGranted;
use Base\Repository\Layout\ImageCrops;
use Base\Service\FileService;
use Base\Service\Flysystem;
use Base\Service\ImageRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

use Base\Service\MediaService;
use Base\Traits\BaseTrait;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[Route("", name:"ux_", priority:-1)]
class MediaController extends AbstractController
{
    use BaseTrait;

    /**
     * @var MediaService
     */
    protected MediaService $mediaService;

    /**
     * @var FileService
     */
    protected mixed $fileService = null;

    /**
     * @var Flysystem
     */
    protected Flysystem $flysystem;

    /**
     * @var ImageCrops
     */
    protected ImageCrops $imageCropRepository;

    /**
     * @var Profiler|null
     */
    protected ?Profiler $profiler;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var ?bool
     */
    protected ?bool $localCache;

    /**
     * The image route's answers (audit "lesringostars face au master plan",
     * R6): this controller's actions moved there as they were, for any host;
     * the routes, their names and these actions stay.
     */
    protected ImageRoute $imageRoute;

    public function __construct(RequestStack $requestStack, Flysystem $flysystem, MediaService $mediaService, ImageCrops $imageCropRepository, ?Profiler $profiler = null, ?bool $localCache = null)
    {
        $this->imageCropRepository = $imageCropRepository;

        $this->mediaService = $mediaService;
        $this->profiler = $profiler;

        $this->fileService = cast($mediaService, FileService::class);
        $this->flysystem = $flysystem;

        $this->localCache = $localCache ?? true;
        $this->requestStack = $requestStack;

        $this->imageRoute = new ImageRoute($mediaService, $flysystem, $imageCropRepository, $this->localCache);
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     */
    public function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        $request = $this->requestStack->getCurrentRequest();
        $isUX = $request ? str_starts_with($request->attributes->get("_route"), "ux_") : true;
        if ($this->profiler !== null && $isUX) {
            $this->profiler->disable();
        }

        $cacheless = !$this->localCache ? "_cacheless" : "";
        return parent::redirectToRoute($route . $cacheless, $parameters, $status);
    }

    /**
     * The image route's answers for this request (Base\Service\ImageRoute):
     * the local cache this controller uses, the profiler kept only off the
     * ux_ routes, as before.
     */
    protected function route(): ImageRoute
    {
        return $this->imageRoute->with($this->localCache, !$this->isUX());
    }

    protected function isUX(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request ? str_starts_with($request->attributes->get("_route"), "ux_") : true;
    }

    /** A redirection of the route: the profiler left aside on an ux_ route, as redirectToRoute() did. */
    protected function answer(Response $response): Response
    {
        if ($response instanceof RedirectResponse && $this->profiler !== null && $this->isUX()) {
            $this->profiler->disable();
        }

        return $response;
    }

    #[Route("/contents/{data}", name: "serve", requirements: ["data" => ".+"])]
    public function Serve($data): Response
    {
        return $this->answer($this->route()->contents($data));
    }

    #[Route("/images/cacheless/cropper/{identifier}/{data}/image.{extension}", name: "imageCropExtension_cacheless", requirements: ["data" => ".+"])]
    #[Route("/images/cacheless/cropper/{identifier}/{data}", name: "imageCrop_cacheless", requirements: ["data" => ".+"])]
    public function ImageCropCacheless($data, string $identifier, ?string $extension = null): Response
    {
        $this->localCache = false;
        return $this->ImageCrop($data, $identifier, $extension);
    }

    #[Route("/images/cropper/{identifier}/{data}/image.{extension}", name: "imageCropExtension", requirements: ["data" => ".+"])]
    #[Route("/images/cropper/{identifier}/{data}", name: "imageCrop", requirements: ["data" => ".+"])]
    public function ImageCrop($data, string $identifier, ?string $extension = null): Response
    {
        return $this->answer($this->route()->imageCrop($data, $identifier, $extension));
    }

    #[Route("/images/cacheless/{data}/image.webp", name: "imageWebp_cacheless", requirements: ["data" => ".+"])]
    public function ImageWebpCacheless($data): Response
    {
        $this->localCache = false;
        return $this->ImageWebp($data);
    }

    #[Route("/images/debug/{data}/image.{extension}", name: "debug_imageExtension", requirements: ["data" => ".+"])]
    #[Route("/images/debug/{data}", name: "debug_image", requirements: ["data" => ".+"])]
    #[IsGranted("ROLE_EDITOR")]
    public function ImageDebug($data, ?string $extension = null): Response
    {
        return $this->Image($data, $extension, true);
    }

    #[Route("/images/cacheless/{data}/image.{extension}", name: "imageExtension_cacheless", requirements: ["data" => ".+"])]
    #[Route("/images/cacheless/{data}", name: "image_cacheless", requirements: ["data" => ".+"])]
    public function ImageCacheless($data, ?string $extension = null): Response
    {
        $this->localCache = false;
        return $this->Image($data, $extension);
    }

    #[Route("/images/{data}/image.webp", name: "imageWebp", requirements: ["data" => ".+"])]
    public function ImageWebp($data): Response
    {
        return $this->answer($this->route()->imageWebp($data));
    }

    #[Route("/images/{data}/image.svg", name: "imageSvg", requirements: ["data" => ".+"])]
    public function ImageSvg($data): Response
    {
        return $this->answer($this->route()->imageSvg($data));
    }

    #[Route("/images/{data}/image.{extension}", name: "imageExtension", requirements: ["data" => ".+"])]
    #[Route("/images/{data}", name: "image", requirements: ["data" => ".+"])]
    public function Image($data, ?string $extension = null, bool $debug = false): Response
    {
        return $this->answer($this->route()->image($data, $extension, $debug));
    }
}
