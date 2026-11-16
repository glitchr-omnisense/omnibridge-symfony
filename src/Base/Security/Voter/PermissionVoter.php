<?php

namespace Base\Security\Voter;

use Base\Auth\Policy\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** The permission tags ("ARTICLE.PUBLISH", "ARTICLE.*"): omnibase's Permission policy (step F5e of its plan). */
class PermissionVoter extends Voter
{
    private readonly Permission $policy;

    public function __construct()
    {
        $this->policy = new Permission();
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->policy->supports($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->policy->decide($attribute, $token->getUser());
    }
}
