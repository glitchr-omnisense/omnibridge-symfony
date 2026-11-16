<?php

namespace Base\Security\Voter;

use Base\Auth\Policy\Complaint;
use Omnibridge\Symfony\Contract\HierarchyRoles;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/** COMPLAINT_READ / COMPLAINT_HANDLE: omnibase's Complaint policy on the role hierarchy (step F5e of its plan). */
class ComplaintVoter extends Voter
{
    public const READ = Complaint::READ;
    public const HANDLE = Complaint::HANDLE;

    private readonly Complaint $policy;

    public function __construct(private readonly AccessDecisionManagerInterface $decisions, RoleHierarchyInterface $hierarchy, string $readerRole = 'ROLE_MODERATOR', string $topRole = 'ROLE_SUPERADMIN')
    {
        $this->policy = new Complaint(new HierarchyRoles($hierarchy), $readerRole, $topRole);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->policy->supports($attribute, $subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->policy->decide($attribute, $subject, $token->getUser(), fn (string $tag): bool => $this->decisions->decide($token, [$tag]));
    }
}
