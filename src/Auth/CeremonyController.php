<?php

namespace Omnibridge\Symfony\Auth;

use Omniauth\Factor;
use Omniauth\Method\Challenge;
use Omniauth\Omniauth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Twig\Environment;

/**
 * The steps of a sign-in that sign nobody in yet (AuthRouteLoader's routes):
 * the second factor's page, a passkey's request options, the trip out to an
 * identity provider. The verifications themselves are OmniauthAuthenticator's.
 *
 * The second factor's page renders the application's template with the very
 * variables scheb/2fa's FormController gave it (twoFactorProvider,
 * availableTwoFactorProviders, checkPathUrl, authCodeParameterName,
 * displayTrustedOption, isCsrfProtectionEnabled, logoutPath...), the factors
 * under scheb's names ("email" for omniauth's "email-code"): a template
 * written for scheb/2fa renders unchanged. The code by e-mail is sent when
 * its page is first shown (as scheb/2fa sent it), and again once it expired.
 */
final class CeremonyController
{
    /** omniauth's factors under the names scheb/2fa gave its providers. */
    public const PROVIDER_NAMES = ['totp' => 'totp', 'email-code' => 'email', 'backup-code' => 'backup-code'];

    /** @param array<string, mixed> $config OmniauthExtension's, resolved */
    public function __construct(
        private readonly Omniauth $omniauth,
        private readonly Contexts $contexts,
        private readonly UrlGeneratorInterface $urls,
        private readonly TokenStorageInterface $tokens,
        private readonly array $config,
        private readonly ?Environment $twig = null,
        private readonly ?LogoutUrlGenerator $logout = null,
    ) {
    }

    public function secondFactor(Request $request): Response
    {
        $attempt = $this->omniauth->attempt();
        if (!$this->tokens->getToken() instanceof PendingToken || null === $attempt || [] === $attempt->offered()) {
            $this->omniauth->abandon();
            $this->tokens->setToken(null);

            return new RedirectResponse($this->urls->generate($this->config['login_route']));
        }
        $offered = $attempt->offered();
        $session = $request->getSession();
        $preferred = $request->query->get('preferProvider');
        if (\is_string($preferred) && null !== ($factor = Factor::tryFrom(array_flip(self::PROVIDER_NAMES)[$preferred] ?? $preferred)) && \in_array($factor, $offered, true)) {
            $session->set(OmniauthAuthenticator::CHOSEN, $factor->value);
        }
        $chosen = Factor::tryFrom((string) $session->get(OmniauthAuthenticator::CHOSEN));
        $factor = null !== $chosen && \in_array($chosen, $offered, true) ? $chosen : OmniauthAuthenticator::firstOf($offered);
        if (null === $factor) {
            throw new \LogicException('A second factor is asked, none is offered.');
        }
        $session->set(OmniauthAuthenticator::CHOSEN, $factor->value);
        if (Factor::EmailCode === $factor && !\is_int($attempt->kept(Factor::EmailCode))) {
            $this->omniauth->begin(Factor::EmailCode, $this->contexts->of($request));
        }

        $error = $session->get(OmniauthAuthenticator::SECOND_FACTOR_ERROR);
        $session->remove(OmniauthAuthenticator::SECOND_FACTOR_ERROR);
        $second = $this->config['second_factor'];
        $providers = array_values(array_map(static fn (Factor $f) => self::PROVIDER_NAMES[$f->value] ?? $f->value, array_filter($offered, static fn (Factor $f) => Factor::BackupCode !== $f)));
        $logoutPath = null;
        try {
            $logoutPath = $this->logout?->getLogoutPath();
        } catch (\InvalidArgumentException) {
        }

        if (null === $this->twig) {
            throw new \LogicException('The second factor\'s page is a Twig template: install symfony/twig-bundle.');
        }

        return new Response($this->twig->render($second['template'], [
            'twoFactorProvider' => self::PROVIDER_NAMES[$factor->value] ?? $factor->value,
            'availableTwoFactorProviders' => $providers,
            'authenticationError' => \is_array($error) ? $error['key'] : null,
            'authenticationErrorData' => \is_array($error) ? $error['data'] : [],
            'displayTrustedOption' => [] !== $this->omniauth->methods()->ambient(),
            'authCodeParameterName' => $second['code'],
            'trustedParameterName' => $second['trusted'],
            'isCsrfProtectionEnabled' => null !== $second['csrf'],
            'csrfParameterName' => $second['csrf']['field'] ?? '_csrf_token',
            'csrfTokenId' => $second['csrf']['intent'] ?? 'two_factor',
            'checkPathRoute' => $second['check_route'],
            'checkPathUrl' => $this->urls->generate($second['check_route']),
            'logoutPath' => $logoutPath,
        ]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    /** A passkey's request options, in the JSON web-auth/webauthn-symfony-bundle answered: {"status": "ok", "errorMessage": "", ...the options}. */
    public function passkeyOptions(Request $request): Response
    {
        $challenge = $this->omniauth->begin(Factor::Passkey, $this->contexts->of($request));
        if ($challenge->hasFailed() || Challenge::JSON !== $challenge->kind) {
            return new JsonResponse(['status' => 'failed', 'errorMessage' => (string) ($challenge->reason ?? $challenge->failure?->value)], Response::HTTP_BAD_REQUEST);
        }
        $options = json_decode((string) $challenge->payload, true, 32, \JSON_THROW_ON_ERROR);

        return new JsonResponse(['status' => 'ok', 'errorMessage' => ''] + $options, Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    /** The trip out to an identity provider: its authorization address, the state, PKCE and nonce kept in the attempt. */
    public function oauthStart(Request $request, string $provider): Response
    {
        if (!$this->omniauth->methods()->has(Factor::OAuth)) {
            throw new NotFoundHttpException('No identity provider is served.');
        }
        $challenge = $this->omniauth->begin(Factor::OAuth, $this->contexts->of($request), ['provider' => $provider]);
        if ('unknown-provider' === $challenge->reason) {
            throw new NotFoundHttpException(\sprintf('The "%s" provider is not served.', $provider));
        }
        if ($challenge->hasFailed() || Challenge::REDIRECT !== $challenge->kind) {
            return new RedirectResponse($this->urls->generate($this->config['login_route']));
        }

        return new RedirectResponse((string) $challenge->payload);
    }

    /** A check address asked otherwise than the authenticator answers (a GET of the second factor's check): back to its page. */
    public function check(Request $request): Response
    {
        $route = $this->tokens->getToken() instanceof PendingToken ? $this->config['second_factor']['form_route'] : $this->config['login_route'];

        return new RedirectResponse($this->urls->generate($route));
    }
}
