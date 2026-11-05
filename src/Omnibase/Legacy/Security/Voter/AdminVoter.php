<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Security\Voter;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AdminVoter extends Voter
{
    public const BACKEND = "BACKEND";

    /**
     * @var RouterInterface
     * */
    protected RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Voter only support "User" objects and one specific ballot type..
        // Check attribute first to short-circuit expensive route resolution
        return $attribute == self::BACKEND && ($this->router->isAdmin() || $this->router->isProfiler());
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        //
        // Check if current user is authenticated
        $user = $token->getUser();
        return $user && $user->isGranted("ROLE_ADMIN");
    }
}
