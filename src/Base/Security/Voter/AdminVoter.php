<?php

namespace Base\Security\Voter;

use Base\Auth\Policy\Admin;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** BACKEND on the back office's and the profiler's pages: omnibase's Admin policy (step F5e of its plan). */
class AdminVoter extends Voter
{
    public const BACKEND = Admin::BACKEND;

    protected Admin $policy;

    public function __construct(protected RouterInterface $router)
    {
        $this->policy = new Admin();
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->policy->supports($attribute, fn (): bool => $this->router->isAdmin() || $this->router->isProfiler());
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->policy->decide($token->getUser());
    }
}
