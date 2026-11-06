<?php

namespace Omnibridge\Symfony\Omnibase\Subscriber;

use App\Entity\User;
use Base\Entity\User as BaseUser;
use Base\Request\RouterPage;
use Base\Request\Rule\Analytics;
use Omnibridge\Symfony\Omnibase\Legacy\Subscriber\AnalyticsSubscriber as Legacy;
use Symfony\Component\HttpKernel\Event\RequestEvent;

if (!class_exists(Analytics::class)) {
    // omnisense/omnibase 4.x: the rule is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    class AnalyticsSubscriber extends Legacy
    {
    }
} else {
    /**
     * The back office's figures on Symfony's kernel request, answered by omnibase's Analytics rule (step F5e of
     * its plan, slice 2) and put in Twig's globals: the accounts online and active, then Google Analytics'
     * basics where its service is set up (a remote API: the back office's main requests only).
     */
    class AnalyticsSubscriber extends Legacy
    {
        public function onUserRequest(RequestEvent $event)
        {
            if (is_instanceof(User::class, BaseUser::class) && Analytics::applies(new RouterPage($this->router), $event->isMainRequest())) {
                foreach ($this->rule()->users($this->tokenStorage->getToken()?->getUser(), User::getOnlineDelay(), $this->twig->getGlobals()) as $name => $value) {
                    $this->twig->addGlobal($name, $value);
                }
            }
        }

        public function onGoogleAnalyticsRequest(RequestEvent $event)
        {
            if (Analytics::applies(new RouterPage($this->router), $event->isMainRequest()) && isset($this->gaService) && $this->gaService->isEnabled()) {
                $this->twig->addGlobal('user_analytics', $this->rule()->google($this->gaService->getBasics(), $this->twig->getGlobals()));
            }
        }

        private function rule(): Analytics
        {
            return new Analytics($this->userRepository, $this->translator);
        }
    }
}
