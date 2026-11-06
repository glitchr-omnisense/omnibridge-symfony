<?php

namespace Omnibridge\Symfony\Omnibase\Controller\UX;

use Base\Attributes\Attribute\IsGranted;
use Base\Repository\Layout\ImageCrops;
use Base\Service\Flysystem;
use Base\Service\ImageRoute;
use Base\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The image routes under Symfony: omnibase's Base\Service\ImageRoute answers each (audit R6; plan "omnibase agnostic",
 * step F5e slice 5b) - the stored file, the image resized or cropped and kept in the local cache (not on a cacheless
 * route), the redirection to the right extension -, as omnibridge/laravel's ImageController asks it; Symfony's are the
 * routes (the debug image an editor's, through Security) and the profiler, left out of an ux_ route's answer.
 * MediaService calls these actions by their names to resolve an image's address.
 */
#[Route("", name: "ux_", priority: -1)]
class MediaController extends AbstractController
{
    protected ImageRoute $imageRoute;

    public function __construct(protected RequestStack $requestStack, Flysystem $flysystem, MediaService $mediaService, ImageCrops $imageCropRepository, protected ?Profiler $profiler = null, ?bool $localCache = null)
    {
        $this->imageRoute = new ImageRoute($mediaService, $flysystem, $imageCropRepository, $localCache ?? true);
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
        return $this->answer($this->route(false)->imageCrop($data, $identifier, $extension));
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
        return $this->answer($this->route(false)->imageWebp($data));
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
        return $this->answer($this->route(false)->image($data, $extension));
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

    /** The image route for this request: the local cache (off on a cacheless route), the profiler kept off the ux_ routes. */
    protected function route(?bool $localCache = null): ImageRoute
    {
        return $this->imageRoute->with($localCache, !$this->isUX());
    }

    protected function isUX(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return !$request || str_starts_with((string) $request->attributes->get("_route"), "ux_");
    }

    /** A redirection of the route: the profiler left aside on an ux_ route. */
    protected function answer(Response $response): Response
    {
        if ($response instanceof RedirectResponse && null !== $this->profiler && $this->isUX()) {
            $this->profiler->disable();
        }

        return $response;
    }
}
