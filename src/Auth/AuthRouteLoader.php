<?php

namespace Omnibridge\Symfony\Auth;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of omniauth's ceremonies, at the addresses and under the names
 * an application configured (omniauth.yaml) - by default those scheb/2fa,
 * hwi/oauth-bundle and web-auth/webauthn-symfony-bundle had, so the pages and
 * scripts that name them do not change: the second factor's page and its
 * check (2fa_login, 2fa_login_check), a passkey's options and its sign-in,
 * each identity provider's trip out and return (oauth_<name>,
 * oauth_<name>_check). An application imports them:
 *
 *     omniauth: { resource: ., type: omniauth }
 *
 * The checks are answered by OmniauthAuthenticator before any controller;
 * theirs only sends a stray GET back to its page.
 */
final class AuthRouteLoader extends Loader
{
    public const TYPE = 'omniauth';

    /** @param array<string, mixed> $config OmniauthExtension's, resolved */
    public function __construct(private readonly array $config, ?string $env = null)
    {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        $controller = CeremonyController::class;
        $second = $this->config['second_factor'];
        $routes->add($second['form_route'], new Route($second['form_path'], ['_controller' => $controller.'::secondFactor'], methods: ['GET']));
        $routes->add($second['check_route'], new Route($second['check_path'], ['_controller' => $controller.'::check']));
        if (null !== ($passkey = $this->config['passkey'] ?? null)) {
            $routes->add($passkey['options_route'], new Route($passkey['options_path'], ['_controller' => $controller.'::passkeyOptions'], methods: ['POST']));
            $routes->add($passkey['route'], new Route($passkey['path'], ['_controller' => $controller.'::check'], methods: ['POST']));
        }
        foreach ($this->config['oauth']['providers'] ?? [] as $name => $provider) {
            $routes->add($provider['start_route'], new Route($provider['start_path'], ['_controller' => $controller.'::oauthStart', 'provider' => $name], methods: ['GET']));
            $routes->add($provider['check_route'], new Route($provider['check_path'], ['_controller' => $controller.'::check'], methods: ['GET']));
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }
}
