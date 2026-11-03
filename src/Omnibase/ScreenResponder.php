<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Screen\Answer;
use Base\Screen\ContextProvider;
use Base\Screen\File;
use Base\Screen\Frame;
use Base\Screen\Json;
use Base\Screen\Negotiation;
use Base\Screen\Redirect;
use Base\Screen\Screen;
use Base\Screen\Shell;
use Base\Screen\Template;
use Base\Screen\View;
use Base\Screen\Visit;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Base\View\ViewsAdapter;
use Omnibridge\Contract\Views;
use Omnibridge\Contract\ViewSources;
use Twig\Environment;

/**
 * An action's result answered under Symfony: Symfony's adapter of
 * Base\Screen\Negotiation (step R10 of the audit "lesringostars face au
 * master plan" - the negotiation itself, HTML, JSON, omnibridge/react's
 * protocol and its 409, is framework-free there; omnibridge/laravel's
 * Screen\Responder is Laravel's adapter of it). What a controller hands back
 * - a View (a screen of omnibase, plan "omnibase agnostique" C5), a Template,
 * Json, a Shell, a File, or null for no such page - becomes Symfony's
 * response: the request read as a Visit, the template rendered by Twig, the
 * Answer made a Response (a JsonResponse for JSON data, a BinaryFileResponse
 * for a file), its caching set. A Template rendered alone, and a Shell, keep
 * omnibase's tags out of the page (`_omnibase_inject: false`, as a route
 * default says it).
 */
class ScreenResponder
{
    private ?Negotiation $negotiation = null;

    /** The views the templates render through (plan "omnibase agnostic", F5a; Twig's environment before). */
    private readonly Views $views;

    /** @param Views|Environment $views Twig's environment is deprecated (until 6.0) */
    public function __construct(
        Views|Environment $views,
        private readonly ContextProvider $context,
        private readonly ?string $version = null,
        /** Whether omnibridge/react's protocol is spoken for a View; null: when omnibridge/react is installed. */
        private readonly ?bool $omnibridge = null,
    ) {
        $this->views = ViewsAdapter::of($views);
    }

    /** @param array<string, mixed> $legacy a View's variables of before, for a site's own template */
    public function respond(Request $request, View|Template|Json|Shell|File|Redirect|null $result, array $legacy = [], ?Frame $frame = null): Response
    {
        if (null === $result) {
            throw new NotFoundHttpException();
        }
        if (($result instanceof Template && $result->alone) || $result instanceof Shell) {
            $request->attributes->set('_omnibase_inject', false);
        }

        return self::response($this->negotiation()->answer($result, self::visit($request), $legacy, $frame));
    }

    /** What the client asked, off Symfony's request. */
    public static function visit(Request $request): Visit
    {
        return new Visit(
            $request->getRequestUri(),
            (string) $request->headers->get('Accept', ''),
            $request->headers->get('X-Omnibridge'),
            $request->headers->get('X-Omnibridge-Version'),
        );
    }

    /** Symfony's response of an Answer. */
    public static function response(Answer $answer): Response
    {
        if (null !== $answer->file) {
            $response = new BinaryFileResponse($answer->file, $answer->status, $answer->headers);
        } elseif ($answer->json) {
            $response = JsonResponse::fromJsonString($answer->body, $answer->status, $answer->headers);
        } else {
            $response = new Response($answer->body, $answer->status, $answer->headers);
        }

        $cache = $answer->cache;
        if ($cache->vary) {
            $response->setVary($cache->vary, false);
        }
        if (false === $cache->shared) {
            $response->setPrivate();
        } elseif (true === $cache->shared) {
            $response->setPublic();
        }
        if (null !== $cache->maxAge) {
            $response->setMaxAge($cache->maxAge);
        }
        if ($cache->mustRevalidate) {
            $response->headers->addCacheControlDirective('must-revalidate');
        }
        if ($cache->noStore) {
            $response->headers->addCacheControlDirective('no-store');
        }

        return $response;
    }

    /**
     * The variables a page had before its view model, given only to a template
     * a site overrides (its templates/ folder, templates/bundles/...): one
     * read from $own (the core's templates by default) reads its screen alone,
     * and the variables - their queries - are not made at all.
     *
     * @param \Closure(): array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public function legacy(string $template, \Closure $variables, ?string $own = null): array
    {
        // The core's own templates (omnisense/omnibase's templates/, beside its src/ where BaseBundle is).
        $own ??= \Base\BaseBundle::getBundleDir().'/templates';
        $own = realpath($own) ?: $own;
        $path = $this->views instanceof ViewSources ? (string) $this->views->sourcePath($template) : '';

        return str_starts_with(realpath($path) ?: $path, rtrim($own, '/').'/') ? [] : $variables();
    }

    /** @return array<string, mixed> the frame's props and the screen's, as a front end reads them */
    public function props(Frame $frame, View $view): array
    {
        return $this->negotiation()->props($frame, $view);
    }

    /** A screen whose props would take a key of the frame's is refused: its own would be lost in the JSON. */
    public static function guard(Screen $screen): void
    {
        Negotiation::guard($screen);
    }

    public function negotiation(): Negotiation
    {
        return $this->negotiation ??= new Negotiation(fn (string $template, array $context): string => $this->views->render($template, $context), $this->context, $this->version, $this->omnibridge);
    }
}
