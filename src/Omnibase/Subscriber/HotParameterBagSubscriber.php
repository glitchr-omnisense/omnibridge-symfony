<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use Base\Service\HotParameters;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The settings stored in the database laid over the parameters on the first
 * request or command (HotParameters::warm(), omnisense/omnibase's): a database
 * that does not answer yet (not created, a console before its migrations)
 * leaves the configuration alone, the overlay not ready.
 *
 * Moved from omnisense/omnibase's Base\Subscriber\HotParameterBagSubscriber
 * (plan "omnibase agnostic", step F5c), its former name an alias the core
 * declares; what it does is the core's warm(), this is its kernel and
 * console side.
 */
class HotParameterBagSubscriber implements EventSubscriberInterface
{
    protected ParameterBagInterface $parameterBag;

    protected SettingBagInterface $settingBag;

    public function __construct($parameterBag, SettingBagInterface $settingBag)
    {
        $this->parameterBag = $parameterBag;
        $this->settingBag = $settingBag;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onRequest', 2048],
            KernelEvents::REQUEST => ['onRequest', 512],
        ];
    }

    public function onRequest($event): void
    {
        if ($event instanceof RequestEvent && !$event->isMainRequest()) {
            return;
        }
        if (!$this->parameterBag instanceof HotParameters || $this->parameterBag->isReady()) {
            return;
        }

        \benchmark_start();

        try {
            $this->parameterBag->warm($this->settingBag);
        } catch (DBALException) {
        }
    }
}
