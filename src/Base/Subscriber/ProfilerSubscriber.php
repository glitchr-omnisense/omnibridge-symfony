<?php

namespace Base\Subscriber;

use Base\Routing\AdvancedRouterInterface;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfilerSubscriber implements EventSubscriberInterface
{
    /**
     * @var AdvancedRouterInterface
     */
    protected AdvancedRouterInterface $router;

    public function __construct(AdvancedRouterInterface $router)
    {
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => ['onKernelResponse'],
        ];
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($this->router->isProfiler($event) && !$this->router->isDebug()) {
            throw new NotFoundHttpException("Page not found.");
        }
    }

    /**
     * @param ResponseEvent $event
     * @return bool
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$this->router->isDebug()) {
            return false;
        }
        if ($this->router->isProfiler($event)) {
            return false;
        }
        if ($this->router->isUX()) {
            return false;
        }
        if (!$event->getRequest()->isXmlHttpRequest()) {
            return false;
        }

        $response = $event->getResponse();
        $response->headers->set('Symfony-Debug-Toolbar-Replace', true);

        return true;
    }
}
