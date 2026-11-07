<?php

namespace Omnibridge\Symfony\Omnibase\Controller;

use Base\Auth\Account\Outcome;
use Base\Demo\DemoMode;
use Base\Demo\DemoSignIn;
use Base\Repository\Users;
use Base\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserLoaderInterface;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

/**
 * Signing in as a demonstration account in one click: the buttons of the
 * sign-in page's "Demonstration accounts" panel (@Base/demo/_accounts.html.twig).
 *
 * Which account, if any, is omnibase's DemoSignIn's decision (plan "omnibase agnostic", step F5e slice 5):
 * the `demo` environment, the token, a declared account that opens no back office. The route exists in the
 * `demo` environment only; the sign-in is Symfony's authenticator.
 */
class DemoController extends AbstractController
{
    public const CSRF = DemoSignIn::TOKEN;

    #[Route('/login/demo', name: 'security_loginDemo', methods: ['POST'], env: DemoMode::ENVIRONMENT)]
    public function Login(Request $request, DemoSignIn $demo, Users $users, UserAuthenticatorInterface $userAuthenticator, LoginFormAuthenticator $authenticator): Response
    {
        // The users by omnibase's interface, the persistence chosen's (plan "omnibase agnostic", step F7d-3: on Eloquent,
        // the account signed in is the one its connection is written for), each Security's user loader.
        $outcome = $demo->decide($request->request->get('_token'), $request->request->get('account'), static function (string $identifier) use ($users): ?object {
            try {
                return $users instanceof UserLoaderInterface ? $users->loadUserByIdentifier($identifier) : ($users->oneByEmail($identifier) ?? $users->oneByUsername($identifier));
            } catch (UserNotFoundException) {
                return null;
            }
        });

        return match ($outcome->kind) {
            Outcome::SIGNED_IN => $userAuthenticator->authenticateUser($outcome->signIn, $authenticator, $request) ?? $this->redirectToRoute($this->getParameter('base.site.index')),
            Outcome::ROUTE => $this->redirectToRoute((string) $outcome->route),
            default => throw $this->createNotFoundException(),
        };
    }
}
