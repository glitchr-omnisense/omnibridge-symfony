<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/** An authenticator that is its firewall's entry point, as omnibase's sign-in forms are: it answers with its name. */
final class SignIn extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly string $name)
    {
    }

    /** The sign-in form's post, as omnibase's: /sign-in?user=<identifier>, the account loaded by the firewall's provider. */
    public function supports(Request $request): bool
    {
        return 'sign-in' === $this->name && '/sign-in' === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        return new \Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport(new \Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge((string) $request->query->get('user')));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new Response('signed in: '.$token->getUserIdentifier());
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $minutes = $exception instanceof \Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException ? ' (throttled, '.self::text($exception->getMessageData()['%minutes%'] ?? '').' min)' : '';

        return new Response('refused: '.$request->query->getString('user').$minutes);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response('entry point: '.$this->name, Response::HTTP_UNAUTHORIZED);
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
