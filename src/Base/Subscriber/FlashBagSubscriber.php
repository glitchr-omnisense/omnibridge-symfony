<?php

namespace Base\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FlashBagSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10]
        ];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $response = $event->getResponse();
        if (!$response instanceof JsonResponse) {
            return;
        }

        // Never into a response a cache may keep and hand to someone else (/api/avatar/codes is public):
        // a member's "Bravo, tu passes niveau 4 !" was folded into it, stored, and gone from their own page.
        // Nor without a session to read, which starting one here would create for every API call.
        if ($response->headers->hasCacheControlDirective('public') || !$event->getRequest()->hasPreviousSession()) {
            return;
        }

        /**
         * @var Session $session
         */
        $session = $event->getRequest()->getSession();
        $flashMessages = $session->getFlashBag()->all();
        if (!empty($flashMessages)) {
            $data = json_decode($response->getContent(), true);
            if (!is_array($data)) {
                $data = ["response" => $data];
            }

            $data['flashbag'] = $flashMessages;
            $response->setData($data);
        }
    }
}
