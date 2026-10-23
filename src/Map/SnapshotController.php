<?php

namespace Omnibridge\Symfony\Map;

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Twig\MapExtension;
use Omnimap\Exception\InvalidArgumentException;
use Omnimap\Snapshot\Snapshots;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The map cache over HTTP (MapRouteLoader's routes):
 *
 * - tile: a tile of a picture kept, as WebP when the route's path ends in
 *   .webp (fitted within public_size), public and cacheable (max_age); with
 *   a public_directory, written there too at the route's own path, so the
 *   web server serves it without PHP the next time;
 * - metadata: the picture's metadata as JSON ({"status": "BAD"} when none);
 * - export: a picture captured by the browser (image: a data: URL, tilesize),
 *   for whoever has the control attribute, with the "omnimap-export" token;
 * - suppress: the picture deleted, and its tiles written in the public
 *   directory with it (ux-google left them served), for the same, with the
 *   "omnimap-suppress" token.
 */
final class SnapshotController
{
    public function __construct(
        private readonly Snapshots $snapshots,
        private readonly ?Access $access,
        private readonly ?Tokens $tokens,
        private readonly string $control,
        private readonly string $tilePath,
        private readonly ?string $publicDirectory,
        private readonly ?int $publicSize,
        private readonly int $maxAge,
        private readonly ?int $tilesize,
    ) {
    }

    public function tile(string $signature, int $id, Request $request): Response
    {
        try {
            $bytes = $this->snapshots->store()->tile($signature, $id);
        } catch (InvalidArgumentException) {
            $bytes = null;
        }
        if (null === $bytes) {
            throw new NotFoundHttpException('No such map tile.');
        }
        $type = 'image/'.('jpg' === $this->snapshots->store()->format() ? 'jpeg' : $this->snapshots->store()->format());
        if (str_ends_with($this->tilePath, '.webp') && 'image/webp' !== $type) {
            $bytes = self::webp($bytes, $this->publicSize);
            $type = 'image/webp';
        }
        if (null !== $this->publicDirectory) {
            $file = rtrim($this->publicDirectory, '/').self::path($this->tilePath, $signature, $id);
            if (is_dir(\dirname($file)) || @mkdir(\dirname($file), 0775, true)) {
                @file_put_contents($file, $bytes);
            }
        }

        $response = new Response($bytes, 200, ['Content-Type' => $type]);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag(md5($bytes));
        $response->isNotModified($request);

        return $response;
    }

    public function metadata(string $signature): JsonResponse
    {
        try {
            $snapshot = $this->snapshots->store()->find($signature);
        } catch (InvalidArgumentException) {
            $snapshot = null;
        }

        return new JsonResponse($snapshot?->metadata() ?? ['status' => 'BAD']);
    }

    public function export(string $signature, Request $request): JsonResponse
    {
        $this->guard(MapExtension::EXPORT, $request);
        $image = $request->request->getString('image');
        if ('' === $image) {
            throw new BadRequestHttpException('No picture was sent.');
        }
        $tilesize = $request->request->getInt('tilesize') ?: $this->tilesize;
        try {
            $snapshot = $this->snapshots->save($signature, $image, $tilesize);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
        $this->forgetPublic($signature);

        return new JsonResponse($snapshot->metadata());
    }

    public function suppress(string $signature, Request $request): JsonResponse
    {
        $this->guard(MapExtension::SUPPRESS, $request);
        try {
            $this->snapshots->delete($signature);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
        $this->forgetPublic($signature);

        return new JsonResponse(['status' => 'OK']);
    }

    private function guard(string $intent, Request $request): void
    {
        if (null === $this->access || !$this->access->isGranted($this->control)) {
            throw new AccessDeniedHttpException('Only whoever may change the map.');
        }
        if (null !== $this->tokens && !$this->tokens->isValid($intent, $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException('Invalid token.');
        }
    }

    /** The tiles written in the public directory for this picture: gone with it, or replaced by the next one served. */
    private function forgetPublic(string $signature): void
    {
        if (null === $this->publicDirectory) {
            return;
        }
        $root = (string) realpath($this->publicDirectory);
        $directory = realpath(\dirname(rtrim($this->publicDirectory, '/').self::path($this->tilePath, $signature, 0)));
        if (false === $directory || '' === $root || !str_starts_with($directory, $root.'/') || !str_contains($directory, $signature)) {
            return;
        }
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($directory);
    }

    private static function path(string $template, string $signature, int $id): string
    {
        return str_replace(['{signature}', '{id}'], [$signature, (string) $id], $template);
    }

    private static function webp(string $bytes, ?int $size): string
    {
        $image = @imagecreatefromstring($bytes);
        if (false === $image) {
            return $bytes;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if (null !== $size && $size > 0 && max($width, $height) > $size) {
            $ratio = $size / max($width, $height);
            $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), \IMG_BICUBIC);
            $image = false === $scaled ? $image : $scaled;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagewebp($image, null, 90);

        return (string) ob_get_clean();
    }
}
