<?php

namespace Base\Subscriber;

use Base\BaseBundle;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Marks the bundle cache as valid early in the request cycle. Sole survivor
 * of the former EagerSubscriber: its eager-construction duty is obsolete now
 * that AttributeReader's constructor is metadata-free (the heavy precompute
 * moved to AttributeCacheWarmer) and BaseTrait statics resolve lazily
 * through the base.runtime locator.
 */
class ValidCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onValidCache', 128]], # must be called before IntegritySubscriber::onKernelRequest()
        ];
    }

    public function onValidCache(KernelEvent $e)
    {
        BaseBundle::getInstance()->markCacheAsValid();
    }
}
