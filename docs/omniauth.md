---
title: Signing in (omniauth)
order: 30
---

# Signing in (omniauth)

Where [omnisense/omniauth](https://github.com/glitchr-omnisense/omniauth) is installed (it is only
suggested), `OmnibridgeBundle` registers omniauth's extension
(`Auth\DependencyInjection\OmniauthExtension`; an omnibase site's `BaseBundle` calls
`OmniauthExtension::register()` too). With no `config/packages/omniauth.yaml`, nothing is wired. With
one, every way of signing in goes through omniauth's conductor and **one Symfony authenticator**.

This page is the reference of the Symfony side: the configuration tree, the services, the routes,
what the authenticator does with each request. What each method does (a password, a sign-in link, an
e-mailed code, backup codes, TOTP, passkeys, identity providers), its stores and events are omniauth's
own manual: [omniauth's Symfony page](https://github.com/glitchr-omnisense/omniauth/blob/1.x/docs/symfony.md)
and its method pages.

## Setting it up

```yaml
# config/packages/security.yaml
security:
    firewalls:
        main:
            custom_authenticators: [Omnibridge\Symfony\Auth\OmniauthAuthenticator]
            entry_point: Omnibridge\Symfony\Auth\OmniauthAuthenticator   # optional: start() sends to login_route
            remember_me: { secret: '%kernel.secret%' }                   # Security's own, used when omniauth says "remember"
            logout: { path: security_logout }
    access_control:
        - { path: ^/login/2fa, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }

# config/routes/omniauth.yaml
omniauth: { resource: ., type: omniauth }

# config/packages/omniauth.yaml - each key turns its method on
omniauth:
    password: { routes: [security_login] }
    link: { route: security_loginWithToken }      # 15 minutes, once, hashed
    email_code: ~
    backup_codes: ~
    trusted_device: ~                              # scheb/2fa's cookie and tokens
    totp: { secret_key: '%env(OMNIAUTH_TOTP_KEY)%' }   # omniauth/otp; the secrets sealed at rest (sodium)
    passkey: { rp_id: '%env(RELYING_PARTY_ID)%' }  # omniauth/webauthn
    oauth: { providers: { google: { client_id: '...', client_secret: '...' } } }   # omniauth/google (the mechanics: omniauth's Omniauth\OAuth\)
```

`totp.secret_key` (base64 of 32 bytes, from the vault or the environment, never in the repository;
the same on every host of one database - the Laravel twin's `config/omniauth.php` reads the same
variable) seals the TOTP secrets at rest: omniauth/otp's `SealedTotpSecrets` over a
`SodiumSecretBox` decorates the `totp_secrets` store, so the method and any page that reads or writes
the store (omnibase's enrolment and its QR code) go through it. Unset, the secrets are kept as the
store has them. A secret kept in clear before is read as it is until the application rewrites its
rows (omnibase sites: a migration).

A provider's return address is, unless it sets one, the **path** of its check route: omniauth puts it
on the host of each trip's request (the providers are built once per worker; an absolute address
made here would stay on the first request's host). Each provider package's factory
(`Omniauth\OAuth\Provider\ProviderFactory`, tag `omniauth.oauth_provider_factory`) makes its own kind;
omniauth/google's is registered when it is installed.

The stores (`Omniauth\Contract\Accounts`... the passkeys, the sender) are the application's, by their
contracts' names or those `omniauth.yaml`'s `stores` names; omniauth's events go to Symfony's
dispatcher (listen to `Omniauth\Event\SignedIn`...). The accounts must be Symfony users
(`UserInterface`): the authenticator refuses any other with a `LogicException`.

## The configuration tree

Dumped with Symfony's reference dumper (`bin/console config:dump-reference omniauth`) on a kernel of
FrameworkBundle, SecurityBundle, TwigBundle and OmnibridgeBundle, in the package's image (PHP 8.4,
Symfony 8.1.9, 2026-10-27):

```yaml
# Default configuration for extension with alias: "omniauth"
omniauth:

    # Keys the e-mailed codes' hashes and the trusted devices' tokens (scheb/2fa signed them with kernel.secret)
    secret:               '%kernel.secret%'

    # The sign-in page: where a refused or abandoned attempt goes back to
    login_route:          security_login
    stores:
        accounts:             Omniauth\Contract\Accounts
        passwords:            Omniauth\Contract\Passwords
        link_tokens:          Omniauth\Contract\LinkTokens
        email_codes:          Omniauth\Contract\EmailCodes
        backup_codes:         Omniauth\Contract\BackupCodes
        trusted_version:      Omniauth\Contract\TrustedVersion
        totp_secrets:         Omniauth\Contract\TotpSecrets
        identities:           Omniauth\Contract\Identities
        passkeys:             Omniauth\WebAuthn\Contract\Passkeys
        sender:               Omniauth\Contract\Sender
    password:
        enabled:              true

        # The routes a sign-in form posts to
        routes:

            # Default:
            - security_login

        # The form's name, its fields read under it; null: at the top of the request
        form:                 null
        identifier:           _username
        password:             _password
        remember:             _remember_me
        target_path_parameter: _target_path
        default_target_path:  /

        # The class whose hasher security.yaml sets (password_hashers): the same hasher checks the passwords
        user_class:           null
        csrf:
            enabled:              true
            field:                _csrf_token
            intent:               authenticate
    link:
        enabled:              false
        lifetime:             900

        # The route of the link's page, which signs in when it carries a token
        route:                null
        parameter:            token
    email_code:
        enabled:              false
        digits:               6
        lifetime:             600
    backup_codes:
        enabled:              false
        count:                8
    trusted_device:
        enabled:              false
        lifetime:             2592000
        cookie:               trusted_device

        # The tokens' "fwl" claim; null: the firewall of the request
        firewall:             null
    totp:
        enabled:              false
        leeway:               0
        period:               30
        digits:               6
        issuer:               null
        server_name:          null

        # The key the secrets are sealed with at rest (omniauth/otp's SodiumSecretBox): base64 of 32 bytes, from the vault or the environment - '%env(OMNIAUTH_TOTP_KEY)%' -, never written in the repository; the same on every host of the database. Unset: the secrets are kept as the store has them
        secret_key:           null
    passkey:
        enabled:              false
        rp_id:                null
        rp_name:              null
        origins:              []
        timeout:              null

        # Security's remember-me after a passkey (the JSON posted may say otherwise: "remember")
        remember:             true
        options_path:         /login/passkey/options
        options_route:        passkey_login_options
        path:                 /login/passkey
        route:                passkey_login
    oauth:
        enabled:              false

        # Link an account by an address its provider vouches for: true, false, or a service (omniauth's EmailLinking); null: never one with a second factor
        link_by_email:        null

        # Where an identity no account is linked to goes (its PendingIdentity in the session); null: the sign-in page
        sign_up_route:        null
        providers:

            # Prototype
            name:
                client_id:            ''
                client_secret:        ''

                # An absolute address, or a path each trip's host completes; null: the path of check_route
                redirect_uri:         null
                scopes:               []
                hosted_domain:        null
                type:                 null
                class:                null
                options:              []
                fields:               []

                # A service that is the provider (omniauth's Provider), in place of the settings above
                service:              null
                start_path:           null
                start_route:          null
                check_path:           null
                check_route:          null
    second_factor:
        after:

            # Defaults:
            - password
            - link
            - oauth
        form_path:            /login/2fa
        form_route:           2fa_login
        check_path:           /login/2fa/check
        check_route:          2fa_login_check
        template:             security/login_2fa.html.twig
        code:                 _auth_code
        trusted:              _trusted
        csrf:
            enabled:              true
            field:                _csrf_token
            intent:               two_factor
    throttle:
        enabled:              true

        # A PSR-6 pool (Symfony's cache pools are): the tries counted, and the TOTP steps used
        pool:                 cache.app

        # Tries per window in seconds, by scope (omniauth's CacheThrottle::LIMITS by default): { password: [5, 60] }
        limits:

            # Prototype
            scope:                []

    # The message of a failure, or of "failure:reason" ("%reason%" replaced), as the page translates it
    messages:

        # Prototype
        failure:              ~

```

A key written, even empty (`email_code: ~`), turns its method on (`canBeEnabled()`); `password` and
the two `csrf` blocks and `throttle` are on unless `enabled: false` (`canBeDisabled()`).

### Top level

| Key | Type | Default | Effect |
|---|---|---|---|
| `secret` | string | `%kernel.secret%` | keys the e-mailed codes' hashes (`EmailCodeMethod`) and the trusted devices' tokens (`TrustedDevices`) |
| `login_route` | string | `security_login` | where `start()`, an abandoned or expired second factor, and a refused non-password attempt go back to |
| `stores.<name>` | service id | each contract's own interface name | the store each method reads: `accounts` (`Omniauth\Contract\Accounts`), `passwords`, `link_tokens`, `email_codes`, `backup_codes`, `trusted_version`, `totp_secrets`, `identities`, `passkeys` (`Omniauth\WebAuthn\Contract\Passkeys`), `sender` |
| `messages.<failure>` | string | - | the message of a failure, or of `failure:reason` (`%reason%` replaced), over the defaults below |

### Methods

| Key | Options (default) | Service | Needs |
|---|---|---|---|
| `password` (on) | `routes` (`[security_login]`): the routes a sign-in form posts to; `form` (null): the form's name, its fields read under it, null at the top of the request; `identifier` (`_username`), `password` (`_password`), `remember` (`_remember_me`), `target_path_parameter` (`_target_path`), `default_target_path` (`/`); `user_class` (null): the class whose hasher `security.yaml`'s `password_hashers` sets, so the same hasher checks the passwords; `csrf.enabled` (true), `csrf.field` (`_csrf_token`), `csrf.intent` (`authenticate`) | `omniauth.method.password` (`PasswordMethod`: accounts, passwords, the hasher or null) | - |
| `link` | `lifetime` (900, min 60), `route` (null): the route of the link's page, which signs in when it carries a token; `parameter` (`token`) | `omniauth.method.link` (`LinkMethod`: accounts, link_tokens, sender, lifetime, clock) | the route is the application's own; without `route` the method is mounted but no request is read as a link |
| `email_code` | `digits` (6, 4 to 9), `lifetime` (600, min 60) | `omniauth.method.email_code` (a second factor) | - |
| `backup_codes` | `count` (8, min 1) | `omniauth.method.backup_codes` (a second factor) | - |
| `trusted_device` | `lifetime` (2592000, 30 days, min 60), `cookie` (`trusted_device`), `firewall` (null: the request's firewall; the tokens' `fwl` claim) | `omniauth.method.trusted_device` (`TrustedDevices`) | - |
| `totp` | `leeway` (0), `period` (30), `digits` (6, 6 to 10), `issuer` (null), `server_name` (null), `secret_key` (null) | `omniauth.method.totp` (a second factor; used steps kept in `throttle.pool`); with `secret_key`, `omniauth.totp.box` (lazy `SodiumSecretBox`) and `omniauth.totp.sealed_secrets` decorating the `totp_secrets` store | omniauth/otp |
| `passkey` | `rp_id`, `rp_name` (null), `origins` (`[]`), `timeout` (null), `remember` (true: Security's remember-me after a passkey, unless the posted JSON says `"remember"`), `options_path` (`/login/passkey/options`), `options_route` (`passkey_login_options`), `path` (`/login/passkey`), `route` (`passkey_login`) | `omniauth.method.passkey` (`PasskeyMethod` with a `RelyingParty`) | omniauth/webauthn |
| `oauth` | `link_by_email` (null: `NoSecondFactorLinking`, an account linked by its address only when it has no second factor; true or false; or a service id implementing omniauth's `EmailLinking`); `sign_up_route` (null: the sign-in page); `providers.<name>` | `omniauth.method.oauth` (`OAuthMethod`), `omniauth.oauth.providers` (public) | omnisense/omniauth with `Omniauth\OAuth\`; a provider package |

A method whose package is missing stops the compilation: `omniauth.yaml turns "totp" on, which needs
omniauth/otp: composer require omniauth/otp.` (the same for `passkey`, `oauth`).

### `oauth.providers.<name>`

| Key | Default | Effect |
|---|---|---|
| `client_id`, `client_secret` | `''` | a provider without client id (and without `service`) is not served |
| `redirect_uri` | null | an absolute address, or a path each trip's host completes; null: the path of `check_route` |
| `scopes` | `[]` | handed to the factory |
| `hosted_domain` | null | handed to the factory (Google's `hd`) |
| `type` | null | which factory makes it (`google`); the factory's own default otherwise |
| `class`, `options`, `fields` | null, `[]`, `[]` | accepted and handed to the factories, read by none of the installed ones |
| `service` | null | a service that is the provider (omniauth's `Provider`), in place of the settings above; another class is an `InvalidArgumentException` when the providers are built |
| `start_path`, `start_route` | `/connect/<name>`, `oauth_<name>` | the trip out |
| `check_path`, `check_route` | `/connect/<name>/check`, `oauth_<name>_check` | the return |

Other keys are kept (`ignoreExtraKeys(false)`) and handed to the factory.

> [!WARNING]
> Known defect: `class`, `options` and `fields` are accepted and passed on, but no factory reads
> them (omniauth/google's `GoogleFactory` reads `client_id`, `client_secret`, `redirect_uri`,
> `scopes`, `hosted_domain`, `type`), where they are meant to configure a provider.

### `second_factor`

| Key | Default | Effect |
|---|---|---|
| `after` | `[password, link, oauth]` (omniauth's `EnrolledSecondFactors::AFTER`) | the first factors a second one follows, for an account that has one enrolled |
| `form_path`, `form_route` | `/login/2fa`, `2fa_login` | the second factor's page |
| `check_path`, `check_route` | `/login/2fa/check`, `2fa_login_check` | where its form posts |
| `template` | `security/login_2fa.html.twig` | the page's template, the application's |
| `code` | `_auth_code` | the code's field |
| `trusted` | `_trusted` | the "trust this device" field |
| `csrf.enabled`, `csrf.field`, `csrf.intent` | true, `_csrf_token`, `two_factor` | the form's token, checked through the `Tokens` contract |

### `throttle`

| Key | Default | Effect |
|---|---|---|
| `enabled` | true | false: no `Throttle` given to the conductor |
| `pool` | `cache.app` | a PSR-6 pool: the tries counted, and the TOTP steps used |
| `limits.<scope>` | omniauth's `CacheThrottle::LIMITS`: `password` 5 per 60 s, `link.begin` 3 per 3600 s, `email-code` 5 per 900 s, `email-code.begin` 3 per 900 s, `backup-code` 5 per 900 s, `totp` 5 per 900 s, any other (`*`) 5 per 60 s | `[tries, seconds]`, replacing the default scope by scope; a missing number is 5 or 60 |

## The services

| Id | Class | What it is |
|---|---|---|
| `Omniauth\Omniauth` (public) | `Omniauth` | the conductor: accounts, the methods, the state, the policy, the throttle (or null), `event_dispatcher`, `clock` (null when absent) |
| `omniauth.methods` (alias `Omniauth\Method\Methods`) | `Methods` | every service tagged `omniauth.method` |
| `omniauth.second_factors` | `Methods` | the services tagged `omniauth.second_factor` (email code, backup codes, TOTP) |
| `omniauth.policy.default` (alias `Omniauth\Contract\SecondFactorPolicy`) | `EnrolledSecondFactors` | the second-factor rule; an application decorates the alias |
| `Omnibridge\Symfony\Auth\SessionState` (alias `Omniauth\Contract\State`) | | the attempt in the visitor's session (through the request stack); nothing kept without a session |
| `Omniauth\CacheThrottle` (alias `Omniauth\Contract\Throttle`) | | over `throttle.pool`, when `throttle.enabled` |
| `Omnibridge\Symfony\Auth\Contexts` | | omniauth's `Context` of a request: origin, host, client IP, user agent, the `trusted_device` cookie, the firewall (`trusted_device.firewall`, else the request's firewall, else `main`) |
| `Omnibridge\Symfony\Auth\OmniauthAuthenticator` | | the authenticator the firewall names |
| `Omnibridge\Symfony\Auth\PendingVoter` | | tag `security.voter` |
| `Omnibridge\Symfony\Auth\PendingTrustResolver` | | decorates `security.authentication.trust_resolver` (ignored when absent) |
| `Omnibridge\Symfony\Auth\SecondFactorGate` | | `kernel.event_subscriber` |
| `Omnibridge\Symfony\Auth\CeremonyController` (public) | | tag `controller.service_arguments` |
| `Omnibridge\Symfony\Auth\AuthRouteLoader` | | tag `routing.loader`, type `omniauth` |
| `omniauth.oauth.providers` (public) | `Omniauth\OAuth\Provider\Providers` | `OAuthProviders::create()`: the configured providers through the factories, the `service` ones as they are |
| `omniauth.oauth.factory.google` | `Omniauth\Google\GoogleFactory` | when omniauth/google is installed; any class implementing `ProviderFactory` is autoconfigured with the tag `omniauth.oauth_provider_factory` |
| `omniauth.method.<name>` | the method classes | one per method turned on, tagged `omniauth.method` (and `omniauth.second_factor` for the second factors) |

## The routes

`omniauth: { resource: ., type: omniauth }` loads (`AuthRouteLoader`):

| Name (default) | Path (default) | Methods | Controller |
|---|---|---|---|
| `2fa_login` | `/login/2fa` | GET | `CeremonyController::secondFactor` |
| `2fa_login_check` | `/login/2fa/check` | any | `CeremonyController::check` (the authenticator answers the POST first) |
| `passkey_login_options` | `/login/passkey/options` | POST | `CeremonyController::passkeyOptions` (with `passkey`) |
| `passkey_login` | `/login/passkey` | POST | `CeremonyController::check` (the authenticator answers first) |
| `oauth_<name>` | `/connect/<name>` | GET | `CeremonyController::oauthStart` (`provider` = name), per provider |
| `oauth_<name>_check` | `/connect/<name>/check` | GET | `CeremonyController::check` (the authenticator answers first), per provider |

The names and addresses are those scheb/2fa, web-auth/webauthn-symfony-bundle and hwi/oauth-bundle
had, so the pages and scripts that name them do not change. The password form's route and the link's
route are the application's.

## The authenticator

`OmniauthAuthenticator` (`AbstractAuthenticator`, `AuthenticationEntryPointInterface`, not final)
knows a sign-in request by its route (the router has run before the firewall):

| Request | Factor |
|---|---|
| POST to a route of `password.routes` | password (`identifier`, `password`, `remember` read under `form` or at the top; the CSRF field checked) |
| GET of `link.route` with a non-empty `link.parameter` attribute | link |
| POST to `passkey.route` | passkey (the JSON body; `remember` from the JSON, else `passkey.remember`) |
| POST to `second_factor.check_route` | the factor chosen on the page (else the first offered that is not a backup code, else TOTP); a code not shaped as that factor's digits is read as a backup code when backup codes are offered |
| a provider's `check_route` | oauth (`code`, `state`, `error` from the query) |

A method not turned on is never supported. Then `Omniauth::verify()`, and:

| omniauth's answer | Token | Response |
|---|---|---|
| authenticated | `PostAuthenticationToken` with the account's roles; `RememberMeBadge` when omniauth says remember; every passport carries an `OmniauthBadge` | redirect to a posted or queried `_target_path` of this site (a path, or this host over http/https), else the firewall's saved target, else `default_target_path`; the `trusted_device` cookie set when omniauth gave one (HTTP only, SameSite Lax, secure on HTTPS, `trusted_device.lifetime`); a passkey's `{"status": "ok", "errorMessage": ""}` |
| second factor required | `PendingToken` (the offered factors) | redirect to `second_factor.form_route`; a passkey's JSON with `redirect` |
| refused | `SignInRefused` | password: back to the form's route, `_security.last_error` and `_security.last_username` in the session; link: no response, the route's token parameter emptied so the page answers as for no link; passkey: `{"status": "failed", "errorMessage": ...}`, 401; second factor: back to its page with the error, or to `login_route` when the attempt expired or is gone (the attempt abandoned, the token removed); other: `login_route` |
| identity unknown (oauth) | `SignInRefused` with the identity | the identity kept in the session (`omnibridge.auth.pending_identity`: provider, subject, email, emailVerified, name) and a redirect to `oauth.sign_up_route`, else `login_route` |

`start()` (the entry point) redirects to `login_route`.

The messages (`OmniauthAuthenticator::MESSAGES`, overridden by `messages`), Symfony's security
catalogue's keys so a page translates them as it did:

| Failure | Message |
|---|---|
| `bad-credentials`, `invalid-link`, `refused`, `not-enrolled` | `Invalid credentials.` |
| `bad-code`, `not-offered` | `code_invalid` |
| `expired`, `no-attempt` | `Authentication request could not be processed due to a system problem.` |
| `throttled` | `Too many failed login attempts, please try again in %minutes% minute.` (`%minutes%` given as 1) |
| `account-refused` | `Account is disabled.` |
| `csrf` | `Invalid CSRF token.` |

A key `failure:reason` (`refused:locked`) wins over `failure`; `%reason%` in a message is replaced
by the reason. An unknown failure falls back on `bad-credentials`'s message.

The session keys: `omnibridge.auth.factor` (`OmniauthAuthenticator::CHOSEN`, the second factor
chosen), `omnibridge.auth.second_factor_error` (`SECOND_FACTOR_ERROR`), `omnibridge.auth.pending_identity`
(`PENDING_IDENTITY`), and Security's `_security.last_username`, `_security.last_error`.

## Between two factors

- `PendingToken`: a `TokenInterface` (not an `AbstractToken`, as scheb/2fa's `TwoFactorToken`), no
  role, the offered factors (`offered()`), serialised in the session like any token.
- `PendingVoter`: grants `IS_AUTHENTICATED_2FA_IN_PROGRESS` to a `PendingToken` and to nobody else
  (it abstains otherwise), so an `access_control` line written for scheb/2fa still opens the page.
- `PendingTrustResolver`: `isFullFledged()` is false for a `PendingToken`, so
  `IS_AUTHENTICATED_FULLY` is refused to it.
- `SecondFactorGate`: on `kernel.request` (priority 7, after the firewall) and `kernel.exception`
  (priority 2, before the firewall's exception listener, for an `AccessDeniedException` anywhere in
  the chain), a visitor holding a `PendingToken` is redirected to `second_factor.form_route` - except
  on the second factor's own routes, a page `access_control` opens to `PUBLIC_ACCESS` or whose
  attributes the token is granted, and the firewall's sign-out path.

## The second factor's page

`CeremonyController::secondFactor()` renders `second_factor.template` with the variables scheb/2fa's
form controller gave, so a template written for scheb/2fa renders unchanged:

| Variable | Value |
|---|---|
| `twoFactorProvider` | the factor shown, under scheb's names: `totp`, `email` (omniauth's `email-code`), `backup-code` |
| `availableTwoFactorProviders` | the offered factors but backup codes, under the same names |
| `authenticationError`, `authenticationErrorData` | the last refusal's message key and data, once |
| `displayTrustedOption` | whether an ambient method (trusted devices) is mounted |
| `authCodeParameterName`, `trustedParameterName` | `second_factor.code`, `second_factor.trusted` |
| `isCsrfProtectionEnabled`, `csrfParameterName`, `csrfTokenId` | from `second_factor.csrf` |
| `checkPathRoute`, `checkPathUrl` | the check route and its path |
| `logoutPath` | the firewall's sign-out path, null without one |

`?preferProvider=email` (or any offered factor) chooses the factor. The e-mailed code is sent when its
page is first shown, and again once it expired. Without a `PendingToken` or an attempt, the page
abandons the attempt and redirects to `login_route`. The answer is `Cache-Control: no-store`.
Without TwigBundle it throws `LogicException` ("The second factor's page is a Twig template: install
symfony/twig-bundle.").

`passkeyOptions()` answers the passkey's request options as web-auth/webauthn-symfony-bundle did
(`{"status": "ok", "errorMessage": "", ...options}`, or `failed` and 400); `oauthStart()` redirects to
the provider's authorization address (404 when no provider is served or the name is unknown, back to
`login_route` on another failure); `check()` sends a stray GET of a check address back to its page.
