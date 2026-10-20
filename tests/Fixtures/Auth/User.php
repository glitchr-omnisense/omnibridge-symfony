<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Auth;

use Symfony\Component\Security\Core\User\UserInterface;

/** An account of the test application: what each of omniauth's stores keeps of it, on the object itself. */
final class User implements UserInterface
{
    /** @var array<string, int> a link's hash => until when it serves */
    public array $links = [];
    /** @var list<string> */
    public array $backupCodes = [];
    /** @var array<string, string> provider => subject */
    public array $identities = [];
    public bool $emailCodeEnabled = false;
    public ?string $emailCode = null;
    public int $trustedVersion = 0;

    /** @param list<string> $roles */
    public function __construct(
        public readonly string $handle,
        public readonly string $email,
        public ?string $password = null,
        public ?string $totpSecret = null,
        public ?string $refused = null,
        public array $roles = ['ROLE_USER'],
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
