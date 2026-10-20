<?php

namespace Omnibridge\Symfony\Auth;

use Omnibridge\Contract\Tokens;
use Omniauth\Factor;
use Omniauth\Failure;
use Omniauth\Omniauth;
use Omniauth\Proof;
use Omniauth\Result;
use Omniauth\Role;
use Omniauth\Status;
use Omnibridge\Symfony\Auth\Exception\SignInRefused;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * One Symfony authenticator for every way omniauth signs in (plan "omniauth",
 * §3.1): the password form, the sign-in link, a passkey, the second factor's
 * form, the return from an identity provider. It knows the requests by their
 * route (the router has run before the firewall), turns each into omniauth's
 * Proof and Context, checks the forms' CSRF token through omnibridge's Tokens
 * contract, and asks Omniauth::verify(); then it answers as Symfony expects:
 *
 * - Authenticated: a self-validating passport for the account (omniauth has
 *   already checked everything), Security's own remember-me badge when
 *   omniauth says to remember the visitor, the trusted_device cookie when it
 *   gave one; a PostAuthenticationToken.
 * - SecondFactorRequired: a PendingToken (no role, IS_AUTHENTICATED_2FA_IN_PROGRESS
 *   alone) and the second factor's page.
 * - Failed, IdentityUnknown: SignInRefused, its message the page's
 *   (`messages`), the page the visitor came from; a passkey's in JSON, as
 *   web-auth/webauthn-symfony-bundle answered ({"status": "failed", "errorMessage": ...}).
 *
 * Every passport it makes carries an OmniauthBadge: omniauth's events have
 * been heard, and a listener of Security's that does what one of omniauth's
 * does skips it. A refused sign-in link is no link for the page behind: the
 * route's token parameter is emptied and the page answers as for none.
 * It is the firewall's entry point too, when the application names it so
 * (the sign-in page).
 */
class OmniauthAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    /** Where the second factor's page keeps the factor the visitor chose (scheb/2fa's "preferProvider"). */
    public const CHOSEN = 'omnibridge.auth.factor';
    /** Where a refused second factor's error waits for its page. */
    public const SECOND_FACTOR_ERROR = 'omnibridge.auth.second_factor_error';
    /** Where an unknown identity waits for the sign-up (omniauth's PendingIdentity, as an array). */
    public const PENDING_IDENTITY = 'omnibridge.auth.pending_identity';

    private const LAST_USERNAME = '_security.last_username';
    private ?Result $lastResult = null;
    private const LAST_ERROR = '_security.last_error';

    /** The messages a page translates, by omniauth's failure or "failure:reason" ("%reason%" replaced). */
    public const MESSAGES = [
        'bad-credentials' => 'Invalid credentials.',
        'bad-code' => 'code_invalid',
        'invalid-link' => 'Invalid credentials.',
        'expired' => 'Authentication request could not be processed due to a system problem.',
        'throttled' => 'Too many failed login attempts, please try again in %minutes% minute.',
        'account-refused' => 'Account is disabled.',
        'refused' => 'Invalid credentials.',
        'no-attempt' => 'Authentication request could not be processed due to a system problem.',
        'not-offered' => 'code_invalid',
        'not-enrolled' => 'Invalid credentials.',
        'csrf' => 'Invalid CSRF token.',
    ];

    /** @param array<string, mixed> $config OmniauthExtension's, resolved */
    public function __construct(
        private readonly Omniauth $omniauth,
        private readonly Contexts $contexts,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?Tokens $tokens = null,
        private readonly array $config = [],
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return null !== $this->factorOf($request);
    }

    public function authenticate(Request $request): Passport
    {
        $factor = $this->factorOf($request) ?? throw new \LogicException('authenticate() of a request supports() refused.');
        $context = $this->contexts->of($request);
        $proof = $this->proof($factor, $request);
        if (Factor::Password === $factor) {
            $request->getSession()->set(self::LAST_USERNAME, $proof->string('identifier'));
        }

        $result = $this->lastResult = $this->omniauth->verify($factor, $context, $proof);
        if (Status::IdentityUnknown === $result->status) {
            throw new SignInRefused($factor, null, $this->message('bad-credentials', null), [], 'identity-unknown', $result->identity);
        }
        if (!$result->isAuthenticated() && !$result->needsSecondFactor()) {
            throw $this->refusal($factor, $result->failure ?? Failure::BadCredentials, $result->reason);
        }

        $account = $result->account;
        if (!$account instanceof UserInterface) {
            throw new \LogicException(\sprintf('omniauth\'s accounts are Symfony users under Symfony: %s is not a %s.', get_debug_type($account), UserInterface::class));
        }
        $badges = [new OmniauthBadge($result)];
        if ($result->isAuthenticated() && $result->remember) {
            $badges[] = (new RememberMeBadge())->enable();
        }

        return new SelfValidatingPassport(new UserBadge($account->getUserIdentifier(), static fn () => $account), $badges);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $result = $passport->getBadge(OmniauthBadge::class)?->result;
        $user = $passport->getUser();
        if ($result instanceof Result && $result->needsSecondFactor()) {
            return new PendingToken($user, $firewallName, array_map(static fn (Factor $f) => $f->value, $result->offered));
        }

        return new PostAuthenticationToken($user, $firewallName, $user->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $factor = $this->factorOf($request);
        $request->getSession()->remove(self::LAST_ERROR);
        $request->getSession()->remove(self::SECOND_FACTOR_ERROR);
        if ($token instanceof PendingToken) {
            $request->getSession()->remove(self::CHOSEN);
            $url = $this->urls->generate($this->config['second_factor']['form_route']);

            return Factor::Passkey === $factor ? $this->json(true, '', ['redirect' => $url]) : new RedirectResponse($url);
        }

        $response = Factor::Passkey === $factor ? $this->json(true) : new RedirectResponse($this->targetPath($request, $firewallName));
        $request->getSession()->remove(self::CHOSEN);
        $this->trust($request, $response);

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $factor = $this->factorOf($request);
        $session = $request->getSession();
        if (Factor::Passkey === $factor) {
            return $this->json(false, strtr($exception->getMessageKey(), $exception->getMessageData()), [], Response::HTTP_UNAUTHORIZED);
        }
        if (Factor::Link === $factor) {
            // A refused link is no link: the page behind answers as for none (it asks for an address).
            $request->attributes->set($this->config['link']['parameter'], null);
            $params = $request->attributes->get('_route_params', []);
            unset($params[$this->config['link']['parameter']]);
            $request->attributes->set('_route_params', $params);

            return null;
        }
        if (null !== $factor && Role::Second === $factor->role()) {
            $failure = $exception instanceof SignInRefused ? $exception->failure : null;
            if (\in_array($failure, [Failure::Expired, Failure::NoAttempt], true) || !$this->tokenStorage->getToken() instanceof PendingToken) {
                // The attempt is gone: back to the first factor.
                $this->omniauth->abandon();
                $this->tokenStorage->setToken(null);
                $session->set(self::LAST_ERROR, $exception);

                return new RedirectResponse($this->urls->generate($this->config['login_route']));
            }
            $session->set(self::SECOND_FACTOR_ERROR, ['key' => $exception->getMessageKey(), 'data' => $exception->getMessageData()]);

            return new RedirectResponse($this->urls->generate($this->config['second_factor']['form_route']));
        }
        if ($exception instanceof SignInRefused && null !== $exception->identity) {
            $identity = $exception->identity;
            $session->set(self::PENDING_IDENTITY, ['provider' => $identity->provider, 'subject' => $identity->subject, 'email' => $identity->email, 'emailVerified' => $identity->emailVerified, 'name' => $identity->name]);
            if (null !== ($route = $this->config['oauth']['sign_up_route'] ?? null)) {
                return new RedirectResponse($this->urls->generate($route));
            }
        }
        $session->set(self::LAST_ERROR, $exception);
        $route = Factor::Password === $factor ? (string) $request->attributes->get('_route') : $this->config['login_route'];

        return new RedirectResponse($this->urls->generate($route));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urls->generate($this->config['login_route']));
    }

    /** The factor a request brings, by its route; null when this is no sign-in. */
    public function factorOf(Request $request): ?Factor
    {
        $route = $request->attributes->get('_route');
        if (!\is_string($route)) {
            return null;
        }
        $methods = $this->omniauth->methods();
        $post = $request->isMethod('POST');
        if ($post && \in_array($route, $this->config['sign_in']['routes'] ?? [], true) && $methods->has(Factor::Password)) {
            return Factor::Password;
        }
        if (null !== ($link = $this->config['link'] ?? null) && $route === $link['route'] && $request->isMethod('GET')
            && \is_string($token = $request->attributes->get($link['parameter'])) && '' !== $token && $methods->has(Factor::Link)) {
            return Factor::Link;
        }
        if ($post && null !== ($passkey = $this->config['passkey'] ?? null) && $route === $passkey['route'] && $methods->has(Factor::Passkey)) {
            return Factor::Passkey;
        }
        if ($post && $route === ($this->config['second_factor']['check_route'] ?? null)) {
            return $this->secondFactor($request);
        }
        if (isset($this->config['oauth']['routes'][$route]) && $methods->has(Factor::OAuth)) {
            return Factor::OAuth;
        }

        return null;
    }

    /** The second factor a code is for: a backup code when it is not shaped as the chosen factor's codes (scheb/2fa took a backup code in any form). */
    private function secondFactor(Request $request): Factor
    {
        $offered = $this->offered();
        $chosen = Factor::tryFrom((string) $request->getSession()->get(self::CHOSEN));
        $factor = null !== $chosen && \in_array($chosen, $offered, true) ? $chosen : (self::firstOf($offered) ?? Factor::Totp);
        $code = preg_replace('~\s+~', '', (string) $request->request->get($this->config['second_factor']['code'])) ?? '';
        $digits = $this->config['second_factor']['digits'][$factor->value] ?? 6;
        if ($factor !== Factor::BackupCode && \in_array(Factor::BackupCode, $offered, true) && '' !== $code && !preg_match('~^\d{'.$digits.'}$~', $code)) {
            return Factor::BackupCode;
        }

        return $factor;
    }

    /** @return list<Factor> what the attempt in progress offers */
    public function offered(): array
    {
        return $this->omniauth->attempt()?->offered() ?? [];
    }

    /**
     * The factor the second factor's page shows first: the one chosen, else
     * the first offered that is not a backup code.
     *
     * @param list<Factor> $offered
     */
    public static function firstOf(array $offered): ?Factor
    {
        foreach ($offered as $factor) {
            if (Factor::BackupCode !== $factor) {
                return $factor;
            }
        }

        return $offered[0] ?? null;
    }

    private function proof(Factor $factor, Request $request): Proof
    {
        switch ($factor) {
            case Factor::Password:
                $sign = $this->config['sign_in'];
                $form = null !== $sign['form'] ? $request->request->all($sign['form']) : $request->request->all();
                $this->checkCsrf($factor, $sign['csrf'], $form[$sign['csrf']['field'] ?? ''] ?? $request->request->get($sign['csrf']['field'] ?? ''));

                return new Proof([
                    'identifier' => self::scalar($form[$sign['identifier']] ?? null),
                    'password' => self::scalar($form[$sign['password']] ?? null),
                    'remember' => null !== $sign['remember'] && filter_var($form[$sign['remember']] ?? false, \FILTER_VALIDATE_BOOL),
                ]);
            case Factor::Link:
                return new Proof(['token' => (string) $request->attributes->get($this->config['link']['parameter'])]);
            case Factor::Passkey:
                $json = (string) $request->getContent();
                $data = json_decode($json, true);

                return new Proof(['json' => $json, 'remember' => \is_array($data) && \array_key_exists('remember', $data) ? (bool) $data['remember'] : (bool) $this->config['passkey']['remember']]);
            case Factor::OAuth:
                $query = $request->query->all();

                return new Proof(['provider' => $this->config['oauth']['routes'][$request->attributes->get('_route')]] + array_map(self::scalar(...), array_intersect_key($query, array_flip(['code', 'state', 'error']))));
            default:
                $second = $this->config['second_factor'];
                $this->checkCsrf($factor, $second['csrf'], $request->request->get($second['csrf']['field'] ?? ''));

                return new Proof([
                    'code' => self::scalar($request->request->get($second['code'])),
                    'trust' => filter_var($request->request->get($second['trusted'], false), \FILTER_VALIDATE_BOOL),
                ]);
        }
    }

    /** @param array{field: string, intent: string}|null $csrf */
    private function checkCsrf(Factor $factor, ?array $csrf, mixed $token): void
    {
        if (null === $csrf || null === $this->tokens) {
            return;
        }
        if (!$this->tokens->isValid($csrf['intent'], \is_string($token) ? $token : null)) {
            throw new SignInRefused($factor, Failure::Refused, $this->message('csrf', null), [], 'csrf');
        }
    }

    private function refusal(Factor $factor, Failure $failure, ?string $reason): SignInRefused
    {
        $data = Failure::Throttled === $failure ? ['%minutes%' => 1] : [];

        return new SignInRefused($factor, $failure, $this->message($failure->value, $reason), $data, $reason);
    }

    private function message(string $failure, ?string $reason): string
    {
        $messages = ($this->config['messages'] ?? []) + self::MESSAGES;
        $message = (null !== $reason ? $messages[$failure.':'.$reason] ?? null : null) ?? $messages[$failure] ?? self::MESSAGES['bad-credentials'];

        return str_replace('%reason%', (string) $reason, $message);
    }

    /** Where the visitor goes once signed in: the page asked (a posted _target_path of this site, then the firewall's saved one), else the default. */
    private function targetPath(Request $request, string $firewallName): string
    {
        $parameter = $this->config['sign_in']['target_path_parameter'] ?? '_target_path';
        $posted = $request->request->get($parameter) ?? $request->query->get($parameter);
        if (\is_string($posted) && self::isLocal($posted, $request)) {
            return $posted;
        }
        $session = $request->getSession();
        $saved = $session->get('_security.'.$firewallName.'.target_path');
        if (\is_string($saved) && '' !== $saved) {
            $session->remove('_security.'.$firewallName.'.target_path');

            return $saved;
        }

        return $this->config['sign_in']['default_target_path'] ?? '/';
    }

    private static function isLocal(string $url, Request $request): bool
    {
        if ('' === $url) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//') && !str_starts_with($url, '/\\');
        }
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && 0 === strcasecmp($host, $request->getHost()) && \in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /** The trusted_device cookie omniauth handed back, as scheb/2fa set it. */
    private function trust(Request $request, Response $response): void
    {
        // The verification of this very request (authenticate() ran just before).
        $result = $this->lastResult;
        if (null === $result?->trustDevice) {
            return;
        }
        $device = $this->config['trusted_device'];
        $response->headers->setCookie(Cookie::create(
            $device['cookie'],
            $result->trustDevice,
            time() + $device['lifetime'],
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        ));
    }

    /** @param array<string, mixed> $extra */
    private function json(bool $ok, string $error = '', array $extra = [], int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['status' => $ok ? 'ok' : 'failed', 'errorMessage' => $error] + $extra, $status);
    }

    private static function scalar(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
