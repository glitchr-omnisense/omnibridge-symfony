<?php

namespace Omnibridge\Symfony\Omnibase\Security\Voter;

use Base\Auth\Policy\Access;
use Base\Auth\Policy\Launch;
use Base\Auth\Policy\Maintenance;
use Base\Service\LauncherInterface;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

if (!class_exists(Access::class)) {
    // omnisense/omnibase 4.x: the rule is still this bridge's former code (Legacy\, removed once 4.x is no longer served).
    class AccessVoter extends \Omnibridge\Symfony\Omnibase\Legacy\Security\Voter\AccessVoter
    {
    }
} else {
    /** The access restriction's attributes: omnibase's Access, Maintenance and Launch policies (step F5e of its plan). */
    class AccessVoter extends Voter
    {
        public const LAUNCH_ACCESS = Launch::ATTRIBUTE;
        public const MAINTENANCE_ACCESS = Maintenance::ATTRIBUTE;
        public const EXCEPTION_ACCESS = Access::EXCEPTION_ACCESS;
        public const ANONYMOUS_ACCESS = Access::ANONYMOUS_ACCESS;
        public const USER_ACCESS = Access::USER_ACCESS;
        public const ADMIN_ACCESS = Access::ADMIN_ACCESS;

        protected Access $access;
        protected Maintenance $maintenance;
        protected Launch $launch;

        public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, LocalizerInterface $localizer, MaintenanceProviderInterface $maintenanceProvider, LauncherInterface $launcher)
        {
            $this->access = new Access($parameterBag, $localizer, static fn ($url) => $router->getRouteFirewall($url)?->getName(), static fn () => $router->getEnvironment());
            $this->maintenance = new Maintenance($maintenanceProvider, $this->access);
            $this->launch = new Launch($launcher, $this->access);
        }

        protected function supports(string $attribute, mixed $subject): bool
        {
            return \in_array($attribute, Access::ATTRIBUTES, true);
        }

        protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
        {
            return match ($attribute) {
                Maintenance::ATTRIBUTE => $this->maintenance->allows($subject),
                Launch::ATTRIBUTE => $this->launch->allows($subject),
                default => $this->access->decide($attribute, $subject),
            };
        }
    }
}
