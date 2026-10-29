# omnibridge/symfony

The Symfony host of [omnisense/omnibridge](https://github.com/glitchr-omnisense/omnibridge)'s
contracts, and the Symfony side of omnisense/omnibase: the adapters of the host's
contracts over Symfony's own services (router, translator, security, CSRF, kernel),
their container wiring, and the framework packages the core no longer requires.
The mirror of [omnibridge/laravel](https://github.com/glitchr-omnisense/omnibridge-laravel)
for Laravel.

A Symfony site requires it beside the core:

```
composer require omnisense/omnibase omnibridge/symfony
```

omnibase's `BaseBundle` refuses to start without it. A Laravel site does not
require it: it requires omnibridge/laravel instead.

## The contracts

Each adapter is bound under its contract's name and reached through
`Omnibridge\Runtime` (`Runtime::urls()`...) or by autowiring the contract.

| Contract (`Omnibridge\Contract\`) | Symfony (`Omnibridge\Symfony\`) | Laravel (`Omnibridge\Laravel\`) | Answers |
|---|---|---|---|
| `Urls` | `RouterUrls` | `RouterUrls` | the router's `path()`/`url()`, the asset packages' `asset()`; `generate()` for any reference type |
| `Translations` | `TranslatorTranslations` | `CatalogueTranslations` | the `translator`'s `trans()`; `exists()` by its `transExists()` or its catalogue |
| `Environment` | `Contract\KernelEnvironment` | `Contract\AppEnvironment` | `kernel.environment`, `kernel.debug`, `kernel.secret` |
| `Access` | `Contract\SecurityAccess` | `Contract\GateAccess` | the authorization checker, the token's user |
| `Sessions` | `Contract\SecuritySessions` | `Contract\GuardSessions` | the token forgotten, the remember-me cookie cleared (its domain asked of a closure) |
| `Tokens` | `Contract\CsrfTokens` | `Contract\SessionTokens` | the CSRF token manager's token of an intent |
| PSR-20 clock, PSR-14 events | the framework's own services | `Contract\DateClock`, `Contract\DispatcherEvents` | listed when the container has them |
| `Locales`, `Files`, `Routes` | not bound here (see below) | `Contract\AppLocales`, `Contract\DiskFiles`, `Contract\RouterRoutes` | |

`Locales`, `Files` and `Routes` have no generic Symfony answer: an application's
languages, storages and route names are its own. omnibase binds them to its
Localizer, its Flysystem storages and its router (`Base\Bridge\Symfony\*`, below).
Laravel's bridge binds them, and omnibase's own contracts, itself, because Laravel
has none of omnibase's services.

The pages' Twig functions on the contracts: omnibridge/twig's `AccessExtension`
(`is_granted()` on `Access`, `csrf_token()` on `Tokens`) is registered here, after
twig-bridge's `SecurityExtension` and `CsrfExtension` (TwigBundle adds the bridge's
extensions first), so a page asks the same code under Symfony as under Laravel
(omnibridge/laravel's `View\Twig`), answered by the same services. Without
SecurityBundle or CSRF protection the contract is missing and its function left out.

Also here: `FileLinks`, the links of the error pages and the profiler mapped from
the container to the developer's machine (`APP_PATH`), Symfony's own
`FileLinkFormatter` on a mapped format.

## The wiring

- `config/services.php` (`OmnibridgeBundle::SERVICES`): the six adapters, each
  aliased by its contract's name and tagged `omnibridge.adapter`.
- `DependencyInjection\ContractsPass`: the service locator `Omnibridge\Runtime`
  reads (`omnibridge.contracts`), of the contracts whose service exists; an adapter
  whose framework service is missing (no SecurityBundle, no CSRF protection) is
  removed with the aliases that lead to it, so such a container still compiles.
  An application may run it with its own list, former names and id.
- `OmnibridgeBundle`, for a Symfony application without omnibase: imports the
  services, adds the pass, hands the locator to `Runtime` at boot and takes it back
  at shutdown.

An omnibase site does not register `OmnibridgeBundle`: omnibase's `BaseBundle`
imports the same `config/services.php` (giving `RouterUrls` and `SecuritySessions`
omnibase's router), runs the same pass with omnibase's own contracts added (Nouns,
Media, Pictures, Texts, the parameters, the mailer) and seeds the registry itself.

## What moved from omnibase, what stayed (plan "omnibase agnostique", A1)

The rule: an adapter moved when it adapts a contract to Symfony's own services and
nothing of omnibase; it stayed when it reads omnibase's own services or models.
This package does not require omnisense/omnibase, so nothing is circular: the
core's Symfony bundle stands on this package, never the reverse.

Moved (the former names were aliases of the classes here, declared by the core's
`src/Resources/Aliases.php`, and the former service ids aliases of the new ones,
until omnibase 4's final pass removed them - omnibase commit 02682623: name the
classes of the "Now" column):

| Former name | Now | Why |
|---|---|---|
| `Base\Bridge\Symfony\RouterUrls` | `Omnibridge\Symfony\RouterUrls` | a URL generator and asset packages, any Symfony router |
| `Base\Bridge\Symfony\TranslatorTranslations` | `Omnibridge\Symfony\TranslatorTranslations` | Symfony's translator contracts; omnibase's `transExists()` read when present |
| `Base\Bridge\Symfony\KernelEnvironment` | `Omnibridge\Symfony\Contract\KernelEnvironment` | three kernel parameters |
| `Base\Bridge\Symfony\SecurityAccess` | `Omnibridge\Symfony\Contract\SecurityAccess` | Security's checker and token storage |
| `Base\Bridge\Symfony\SecuritySessions` | `Omnibridge\Symfony\Contract\SecuritySessions` | the token storage and the cookie; omnibase's router now a closure for the domain |
| `Base\Bridge\Symfony\CsrfTokens` | `Omnibridge\Symfony\Contract\CsrfTokens` | the CSRF token manager |
| `Base\Bridge\Symfony\FileLinks` | `Omnibridge\Symfony\FileLinks` | ErrorHandler's formatter, `APP_PATH` |
| `Base\DependencyInjection\Compiler\Pass\ContractsPass` (its building) | `Omnibridge\Symfony\DependencyInjection\ContractsPass` | a locator of contracts; omnibase's pass keeps its list and delegates |

Stayed in the core, `Base\` (omnibase's domain under Symfony, D3):

| Class | Why |
|---|---|
| `Base\Bridge\Symfony\LocalizerLocales` | omnibase's Localizer (its `xx-XX` locales, its fallbacks) |
| `Base\Bridge\Symfony\FlysystemFiles` | omnibase's Flysystem service and MediaService's image route |
| `Base\Bridge\Symfony\RouterRoutes` | omnibase's router (localized route names) and IconProvider |
| `Base\Bridge\Symfony\MediaPictures`, `ServiceMedia`, `WysiwygTexts` | omnibase's own contracts over its Twig extensions and MediaService |
| `Base\Bridge\Symfony\SymfonyScreenContext`, `ScreenResponder`, `AccountScreens` | omnibase's screens (`Base\Screen`), its settings, Themizer, account builder |
| `Base\Bridge\Symfony\NotificationMessages` | the Symfony half of omnibase's Notification entity |
| `Base\Bridge\Symfony\SettingBagWarmer` | omnibase's SettingBag |
| `Base\Bridge\Symfony\SecurityUser`, `TwoFactorUser` | what omnibase's `User` declares to Security and scheb/2fa (C7b) |
| `Base\Service\ParameterBag`, `HotParameterBag` | omnibase's parameters, the settings stored in its database over the container's |
| `Base\BaseBundle`, its extension, configuration and compiler passes | omnibase's bundle |
| forms, fields, controllers, subscribers | Symfony by nature, omnibase's (D3) |

`FileLinks::export()` is called by omnibase's `BaseBundle`; nothing here calls it
or registers `FileLinks::formatter()`: without omnibase, an application calls
`export()` early or decorates `debug.file_link_formatter` with `formatter()`
itself ([FileLinks](docs/contracts.md#filelinks)).

## Signing in: `Omnibridge\Symfony\Auth\` (omniauth)

Where [omnisense/omniauth](https://github.com/glitchr-omnisense/omniauth) is installed
(it is only suggested), `OmnibridgeBundle` registers omniauth's extension
(`Auth\DependencyInjection\OmniauthExtension`; an omnibase site's `BaseBundle` calls
`OmniauthExtension::register()` too). With no `config/packages/omniauth.yaml`, nothing
is wired. With one, every way of signing in goes through omniauth's conductor and
**one Symfony authenticator**:

```yaml
# config/packages/security.yaml
firewalls:
    main:
        custom_authenticators: [Omnibridge\Symfony\Auth\OmniauthAuthenticator]
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

`totp.secret_key` (base64 of 32 bytes, from the vault or the environment, never in the
repository; the same on every host of one database - the Laravel twin's `config/omniauth.php` reads
the same variable) seals the TOTP secrets at rest: omniauth/otp's `SealedTotpSecrets` over a
`SodiumSecretBox` decorates the `totp_secrets` store, so the method and any page that reads or writes
the store (omnibase's enrolment and its QR code) go through it. Unset, the secrets are kept as the
store has them. A secret kept in clear before is read as it is until the application rewrites its
rows (omnibase sites: a migration).

A provider's return address is, unless it sets one, the **path** of its check route: omniauth puts
it on the host of each trip's request (the providers are built once per worker; an absolute
address made here would stay on the first request's host). Each provider package's factory
(`Omniauth\OAuth\Provider\ProviderFactory`, tag `omniauth.oauth_provider_factory`) makes its own
kind; omniauth/google's is registered when it is installed.

| Class | Role |
|---|---|
| `OmniauthAuthenticator` | the password form, the sign-in link (a GET of its route with a token), a passkey (JSON), the second factor's form, a provider's return, each turned into omniauth's `Proof` and `Context` and verified; the forms' CSRF token checked by the `Tokens` contract; Security's remember-me when omniauth says so, the `trusted_device` cookie when it gives one; failures as stable messages (`messages`), a passkey's in the JSON web-auth/webauthn-symfony-bundle answered |
| `PendingToken`, `PendingVoter`, `PendingTrustResolver` | the visitor between two factors: no role, never fully signed in, and `IS_AUTHENTICATED_2FA_IN_PROGRESS` (scheb/2fa's name) granted to it alone |
| `SecondFactorGate` | between two factors, every page sends to the second factor's (but those open to all, and the sign-out) |
| `CeremonyController`, `AuthRouteLoader` | the second factor's page (scheb/2fa's template variables), a passkey's options, the trip out to a provider; their routes under the names and at the addresses scheb/2fa, webauthn-symfony-bundle and hwi/oauth-bundle had (`2fa_login`, `2fa_login_check`, `oauth_<name>`, `oauth_<name>_check`; `passkey_login_options`, `passkey_login`) |
| `SessionState`, `Contexts`, omniauth's `CacheThrottle` on a cache pool | the host's duties: the attempt in the session, the request as a `Context`, the tries limited |
| `OmniauthBadge` | on each passport made here: Security's own listeners that do what omniauth's listeners do skip it |

The stores (`Omniauth\Contract\Accounts`... the passkeys, the sender) are the
application's, by their contracts' names or those `omniauth.yaml`'s `stores` names;
omniauth's events go to Symfony's dispatcher (listen to `Omniauth\Event\SignedIn`...).

## Maps: `Omnibridge\Symfony\Map\` (omnimap)

Where omnisense/omnimap is installed (suggested), `config/packages/omnimap.yaml` wires
it: its providers (omnimap/google, omnimap/openstreetmap, registered when installed)
over the application's `http_client`, a PSR-16 view of a cache pool and a PSR-18 client
for geocoder-php; the map cache over a storage service of the application's (any
`Omnimap\Snapshot\Storage`); omnibridge/twig's `MapExtension` (`omnimap(view)`,
`omnimap_export()`, `omnimap_suppress()`); the cache's routes and their controller.

```yaml
# config/routes/omnimap.yaml
omnimap: { resource: ., type: omnimap }

# config/packages/omnimap.yaml
omnimap:
    provider: openstreetmap            # the page's map: a key of providers
    secret: '%env(MAP_SECRET)%'        # signs the pictures' names
    providers:
        openstreetmap: { user_agent: 'MySite/1.0 (+https://example.org)' }
        google: { api_key: '%env(GOOGLE_MAPS_CLIENT_KEY)%', secret: '%env(GOOGLE_MAPS_SECRET)%' }
    cache:
        drive: local.uploads           # an omnidrive drive (Omnidrive\Drives); or storage: an Omnimap\Snapshot\Storage service
        public_directory: '%kernel.project_dir%/public'   # tiles served are written there too
    routes:
        tile: { path: '/omnimap/{signature}/{id}.webp', name: omnimap_tile }
```

| Service | What it is |
|---|---|
| `Omnimap\Registry`, `Omnimap\MapRendererInterface`, `Omnimap\GeocoderInterface` | the providers; the page's; the one `geocoder` names (the page's by default) |
| `Omnimap\Snapshot\Snapshots`, `SnapshotStore` | the cache: pictures named by `secret`, kept on `cache.drive` (an omnidrive drive) or `cache.storage` (`directory`, `pattern`, `format`, `tilesize`), made by the server when the provider makes images (`make: auto`) |
| `Omnimap\MapPrinter`, `Omnibridge\Twig\MapExtension` | the page's map (`cache_only`, `control`) and the buttons |
| `SnapshotController`, `MapRouteLoader` | a tile (WebP when its path says so, fitted within `public_size`, written under `public_directory`), a picture's metadata, the capture (`image`, `tilesize`, `_token` of intent `omnimap-export`) and the deletion (intent `omnimap-suppress`, the public tiles deleted with it), for `control` (ROLE_ADMIN) |


## Storages: `Omnidrive\Drives` (omnidrive)

Where omnisense/omnidrive is installed (suggested), `Omnidrive\Drives` is the application's
storages as omnidrive's drives: an `Omnidrive\Flysystem\OperatorDrives` over the services tagged
`flysystem.storage`, by their `storage` names - the storages of league/flysystem-bundle's
configuration (or omnibase's local ones without the bundle). Nothing is configured a second time:
`config/packages/flysystem.yaml` names them, the code reads and writes them through `Drive`.

```php
public function __construct(private readonly Drives $drives) {}

$this->drives->get('local.uploads')->write('avatars/1.png', $bytes);
```

## Static export: `Omnibridge\Symfony\Static\` (omnibridge/static)

Where omnibridge/static is installed, `config/services.php` registers the
console command `omnibridge:static:build`: omnibridge/static's own command
line (`Console\Cli`), handed this kernel's renderer (each page a visitor's GET
through `$kernel->handle()`, at `--render-origin`, by default the router's
default URI), the router's routes, the container and `public/`.

```sh
bin/console omnibridge:static:build static.php --out=var/static [--verify] [--strict]
```

Run on lesringostars (2026-10-24, the working copy loaded by hand, neither
package installed there yet): the same report as the plain command line, but
for the dev 404 page (Symfony's exception page, its trace).

## Tests

```
docker compose run --rm test
```

PHP 8.4, the latest Symfony: a kernel of FrameworkBundle, SecurityBundle and
OmnibridgeBundle binding each contract (one object through `Runtime` and the
container, the answers of Symfony's services), a kernel without SecurityBundle that
compiles without Access, Sessions and Tokens, and `FileLinks`; `AuthTest`, a kernel
signing in through omniauth alone (each method, the second factor, its page and
nobody else's, refusals, CSRF, throttle, remember-me, trusted device, a simulated
Google, the return address on the host of each request under one container);
`MapTest`, a kernel drawing its map through omnimap (OpenStreetMap's tiles answered
here: the picture made once, a tile served as WebP and written in the public folder,
the metadata, an administrator's capture and deletion with the page's tokens, a
visitor refused). `docker compose run --rm bare`: without omniauth installed, a kernel
compiles and has nothing of `Auth\`.

## Licence

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
