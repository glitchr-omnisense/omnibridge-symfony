<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Security\Voter;

use Base\Entity\User;
use Base\Entity\User\Complaint;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Who reads and answers a complaint (Base\Entity\User\Complaint).
 *
 * COMPLAINT_READ / COMPLAINT_HANDLE, with the complaint as subject (or none: may this user read complaints at all).
 * PermissionVoter alone cannot say it - it matches tags and ignores what they are about - so this voter adds the
 * rule that matters:
 *
 *   - to read complaints at all: the reader role (ROLE_MODERATOR by default), or the permission tag
 *     COMPLAINT.READ / COMPLAINT.HANDLE given to a user or a group (Permission, GrantPermissionAdapter);
 *   - a complaint about somebody of the staff is only for those who OUTRANK them: whose reachable roles hold all of
 *     theirs and more (the role hierarchy decides, not a list here) - moderators do not judge a moderator;
 *   - nobody reads a complaint about themselves, except the top role (ROLE_SUPERADMIN by default), which reads them
 *     all: somebody has to read the ones about the top.
 */
class ComplaintVoter extends Voter
{
    public const READ = "COMPLAINT_READ";
    public const HANDLE = "COMPLAINT_HANDLE";

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly RoleHierarchyInterface $hierarchy,
        private readonly string $readerRole = "ROLE_MODERATOR",
        private readonly string $topRole = "ROLE_SUPERADMIN",
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (self::READ === $attribute || self::HANDLE === $attribute) && (null === $subject || $subject instanceof Complaint);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $reach = $this->reach($user->getRoles());
        if (\in_array($this->topRole, $reach, true)) {
            return true;
        }
        $tag = self::READ === $attribute ? "COMPLAINT.READ" : "COMPLAINT.HANDLE";
        if (!\in_array($this->readerRole, $reach, true) && !$this->decisions->decide($token, [$tag])) {
            return false;
        }
        if (!$subject instanceof Complaint) {
            return true;
        }

        $target = $subject->getTarget();
        if ($target === $user || ($target && $target->getId() === $user->getId())
            || (null !== $subject->getTargetName() && 0 === strcasecmp($subject->getTargetName(), (string) $user))) {
            return false;
        }
        if (!$target instanceof User) {
            return true;
        }
        // Outranking: every role they reach, I reach too - and more.
        $theirs = $this->reach($target->getRoles());
        return [] === array_diff($theirs, $reach) && [] !== array_diff($reach, $theirs);
    }

    /** @return string[] */
    private function reach(array $roles): array
    {
        return array_values(array_unique($this->hierarchy->getReachableRoleNames($roles)));
    }
}
