<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Request\ErrorPage;
use Base\Routing\AdvancedRouterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Throwable;
use Twig\Error\LoaderError;

/**
 * The error page under Symfony: omnibase's Base\Request\ErrorPage decides which (plan "omnibase agnostic", step F5e
 * slice 5b) - the debug page for the site's developers, else the back office's or the site's -; Symfony's error
 * renderer draws the debug page, under the error's own status (403, 422, 404; 500 for what is no HTTP error).
 */
class ErrorController extends AbstractController
{
    public function __construct(
        private readonly HtmlErrorRenderer $htmlErrorRenderer,
        private readonly AdvancedRouterInterface $router,
        private readonly ErrorPage $errorPage,
        private readonly RequestStack $requestStack,
        private readonly ?Profiler $profiler = null,
    ) {
    }

    public function Main(Throwable $exception): Response
    {
        try {
            $page = $this->errorPage->choose($this->router->getRouteName(), $this->requestStack->getCurrentRequest()?->getPathInfo() ?? '');
            if (ErrorPage::RESCUE === $page) {
                return $this->Rescue($exception);
            }
            $flattenException = FlattenException::createFromThrowable($exception);
            foreach ($page as $i => $template) {
                try {
                    return $this->render($template, ['flattenException' => $flattenException]);
                } catch (LoaderError $loaderError) {
                    // The back office's page missing (omnibase/admin not installed): the site's.
                    if ($i === \count($page) - 1) {
                        throw $loaderError;
                    }
                }
            }
        } catch (\Throwable $fatalException) {
            throw new \Exception("Twig rendering engine failed (".trim($fatalException->getMessage(), ".").") following a first exception. (see below)", 500, $exception);
        }

        throw new \LogicException('No error page.');
    }

    public function Rescue(Throwable $exception): Response
    {
        $this->profiler?->disable();
        $flattenException = $this->htmlErrorRenderer->render($exception);

        return new Response($flattenException->getAsString(), $flattenException->getStatusCode(), $flattenException->getHeaders());
    }
}
