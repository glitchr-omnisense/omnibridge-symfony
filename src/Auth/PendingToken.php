<?php

namespace Omnibridge\Symfony\Auth;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The visitor between two factors: omniauth found the account by a first
 * factor and asks a second one (omniauth's Status::SecondFactorRequired).
 * Like scheb/2fa's TwoFactorToken, it is no AbstractToken: the firewall does
 * not compare its (empty) roles with the account's at each request. It
 * carries no role - nothing the account may do is granted to it - and is
 * no full sign-in (PendingTrustResolver); PendingVoter answers
 * IS_AUTHENTICATED_2FA_IN_PROGRESS for it alone, scheb/2fa's name, so an
 * access_control line written for scheb/2fa's TwoFactorToken still opens the
 * second factor's page, and that page only (SecondFactorGate sends every
 * other request there).
 */
final class PendingToken implements TokenInterface
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @param list<string> $offered the second factors the visitor may present (omniauth's Factor values) */
    public function __construct(
        private UserInterface $user,
        private string $firewallName,
        private array $offered = [],
    ) {
        if ('' === $firewallName) {
            throw new \InvalidArgumentException('$firewallName must not be empty.');
        }
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    /** @return list<string> */
    public function offered(): array
    {
        return $this->offered;
    }

    public function __toString(): string
    {
        return \sprintf('PendingToken(user="%s", firewall="%s")', $this->user->getUserIdentifier(), $this->firewallName);
    }

    public function getUserIdentifier(): string
    {
        return $this->user->getUserIdentifier();
    }

    /** None: nothing the account may do is granted before its second factor. */
    public function getRoleNames(): array
    {
        return [];
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): void
    {
        $this->user = $user;
    }

    public function eraseCredentials(): void
    {
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function getAttribute(string $name): mixed
    {
        if (!\array_key_exists($name, $this->attributes)) {
            throw new \InvalidArgumentException(\sprintf('This token has no "%s" attribute.', $name));
        }

        return $this->attributes[$name];
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __serialize(): array
    {
        return [$this->user, $this->firewallName, $this->offered, $this->attributes];
    }

    public function __unserialize(array $data): void
    {
        [$this->user, $this->firewallName, $this->offered, $this->attributes] = $data;
    }
}
