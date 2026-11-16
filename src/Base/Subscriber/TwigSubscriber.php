<?php

namespace Base\Subscriber;

use App\Enum\UserRole;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\ParameterBagInterface;
use Base\Twig\Renderer\Adapter\WebpackTagRenderer;
use Base\Twig\Renderer\Adapter\HtmlTagRenderer;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class TwigSubscriber implements EventSubscriberInterface
{
    /**
     * @var HtmlRagRenderer
     */
    protected $htmlTagRenderer;

    /**
     * @var WebpackTagRenderer
     */
    protected $webpackTagRenderer;

    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var AuthorizationChecker
     */
    protected $authorizationChecker;

    /** * @var bool */
    protected ?bool $autoAppend;

    /** The request attribute (a route default) that keeps a response as it was rendered. */
    public const INJECT = '_omnibase_inject';

    public function __construct(HtmlTagRenderer $htmlTagRenderer, WebpackTagRenderer $webpackTagRenderer, AuthorizationCheckerInterface $authorizationChecker, ParameterBagInterface $parameterBag, AdvancedRouterInterface $router)
    {
        $this->webpackTagRenderer = $webpackTagRenderer;

        $this->htmlTagRenderer = $htmlTagRenderer;

        $this->parameterBag = $parameterBag;
        $this->router = $router;
        $this->authorizationChecker = $authorizationChecker;

        $this->autoAppend = $this->parameterBag->get("base.twig.autoappend");
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
            KernelEvents::RESPONSE => ['onKernelResponse'],
            KernelEvents::EXCEPTION => ['onKernelException'],
        ];
    }

    /**
     * @param ResponseEvent $event
     * @return bool
     */
    private function allowRender(ResponseEvent $event)
    {
        if (!$this->autoAppend) {
            return false;
        }

        // A route that renders a page whole, its own markup and nothing else
        // (a screen also rendered by another framework, plan 0 rule 13), says
        // so with the default `_omnibase_inject: false`: nothing is added to
        // its response (the breakpoints, the queued HTML, the webpack entries).
        if (false === $event->getRequest()->attributes->get(self::INJECT, true)) {
            return false;
        }

        $contentType = $event->getResponse()->headers->get('content-type');
        if ($contentType && !str_contains($contentType, "text/html")) {
            return false;
        }

        if ($this->router->isProfiler()) {
            return false;
        }

        if ($this->exceptionTriggered) {
            return false;
        }

        if (!$event->isMainRequest()) {
            return false;
        }

        return $event->getResponse()->getStatusCode() == 200;
    }

    protected $exceptionTriggered = false;

    public function onKernelException(RequestEvent $event)
    {
        $this->exceptionTriggered = true;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        //
        // Permission based entries
        foreach (UserRole::getPermittedValues() as $role) {
            $tag = "security-" . strtolower(str_lstrip($role, "ROLE_"));
            if (!$this->webpackTagRenderer->hasEntry($tag)) {
                continue;
            }

            if ($this->authorizationChecker->isGranted($role)) {
                $this->webpackTagRenderer->addTag($tag);
            }
        }

        //
        // Breakpoint based entries
        foreach ($this->parameterBag->get("base.twig.breakpoints") ?? [] as $breakpoint) {
            $this->webpackTagRenderer->addBreakpoint($breakpoint["name"], $breakpoint["media"] ?? "all");
        }

        //
        // Webpack "form.editor" entry is used when rendering wysiwyg blocks
        // This loading might maybe be improved by loading the entry on-the-fly when needed only
        $this->webpackTagRenderer->markAsOptional("form.editor", false);

        //
        // Alternative entries
        $this->webpackTagRenderer->addAlternative("async");
        $this->webpackTagRenderer->addAlternative("defer");
    }

    /**
     * @param ResponseEvent $event
     * @return bool
     * @throws \Exception
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $allowRender = $this->allowRender($event);
        if (!$allowRender) {
            return false;
        }

        $response = $event->getResponse();
        if (is_instanceof($response, [StreamedResponse::class, BinaryFileResponse::class])) {
            return false;
        }

        // Encore rest rendering
        $response = $this->webpackTagRenderer->renderFallback($response);

        // Html rest rendering
        $response = $this->htmlTagRenderer->renderFallback($response);

        return true;
    }
}
