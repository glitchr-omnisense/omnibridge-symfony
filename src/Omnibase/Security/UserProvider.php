<?php

namespace Omnibridge\Symfony\Omnibase\Security;

use Base\Security\UserTracker;
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Base\Contract\SecurityUserLoader;
use Base\Repository\Users;

/**
 * The users of the firewalls, by their identifier, refreshed from their row.
 *
 * hwi/oauth-bundle is a suggestion of the core since 4.0: this class no longer
 * names its interface, so it loads without it. With it, the service
 * Omnibridge\Symfony\Omnibase\Security\UserProvider is an OAuthUserProvider (the same class, declared
 * OAuth-aware), see config/services/form.php; loadUserByOAuthUserResponse()
 * stays here (a type in a signature loads nothing).
 */
class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    /**
     * @var UserTracker
     */
    protected UserTracker $userTracker;

    /**
     * @var Users&SecurityUserLoader
     */
    protected Users&SecurityUserLoader $userRepository;

    public function __construct(UserTracker $userTracker, Users&SecurityUserLoader $userRepository)
    {
        $this->userTracker = $userTracker;
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        return $this->userRepository->loadUserByIdentifier($identifier);
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * The session holds a serialized copy of the User; what comes back from
     * it is a detached object with the values of the moment it was stored.
     * Re-reading the row is what makes a change made elsewhere (a level or a
     * balance updated by a nightly command, a role changed in the admin) show
     * on the member's next request instead of their next login - and what
     * makes deleting an account actually end its sessions: no row, no user.
     *
     * A transient user (the OAuth stub of loadUserByOAuthUserResponse(), id
     * 0) has no row to re-read and is returned as it is.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        if ($user->getId()) {
            $fresh = $this->userRepository->find($user->getId());
            if (!$fresh instanceof User) {
                throw new UserNotFoundException(sprintf('User "%s" no longer exists.', $user->getUserIdentifier()));
            }

            $user = $fresh;
        }

        $this->userTracker->updateConnection($user);

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Upgrades the encoded password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // set the new hashed password on the User object
        $user->setPassword($newHashedPassword);
    }


    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        $identityProvider = $response->getResourceOwner()->getName();
        $email = $response->getEmail();

        if ($email && ($user = $this->userRepository->oneByEmail($email))) {

            // Link this IdP to an existing local (or previously-linked) account.
            if ($user->getIdentityProvider() === null) {
                $user->setIdentityProvider($identityProvider);
            }

            return $user;
        }

        // No local account yet: return a transient, unpersisted stub. The oauth firewall's
        // default_target_path (/register) lets the user complete registration (pick a username)
        // before anything is written to the database - isPersistent() (id === 0 here) reflects that.
        $user = new User();
        $user->setId(0);
        $user->setIdentityProvider($identityProvider);
        $user->setEmail($email);
        // The names where the application's user has them (Base\Entity\User has none).
        $accessor = \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor();
        foreach (['firstname' => $response->getFirstName(), 'lastname' => $response->getLastName()] as $property => $value) {
            if ($accessor->isWritable($user, $property)) {
                $accessor->setValue($user, $property, $value);
            }
        }
        $user->verify(true); // Google already verified ownership of this email address

        return $user;
    }
}
