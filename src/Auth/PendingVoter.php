<?php

namespace Omnibridge\Symfony\Auth;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * IS_AUTHENTICATED_2FA_IN_PROGRESS - scheb/2fa's attribute, under the same
 * name - granted to the visitor between two factors (PendingToken) and to
 * nobody else: neither a visitor signed out nor one signed in opens the
 * second factor's page.
 */
final class PendingVoter implements CacheableVoterInterface
{
    public const IS_AUTHENTICATED_2FA_IN_PROGRESS = 'IS_AUTHENTICATED_2FA_IN_PROGRESS';

    public function vote(TokenInterface $token, mixed $subject, array $attributes, mixed $vote = null): int
    {
        if (!\in_array(self::IS_AUTHENTICATED_2FA_IN_PROGRESS, $attributes, true)) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        return $token instanceof PendingToken ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_ABSTAIN;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return self::IS_AUTHENTICATED_2FA_IN_PROGRESS === $attribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }
}
