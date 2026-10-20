# omnibridge/symfony

The Symfony host of [omniscient/omnibridge](https://github.com/glitchr-studio/omnibridge)'s
contracts, and the Symfony side of omniscient/omnibase: the adapters of the host's
contracts over Symfony's own services (router, translator, security, CSRF, kernel),
their container wiring, and the framework packages the core no longer requires.
The mirror of [omnibridge/laravel](https://github.com/glitchr-studio/omnibridge-laravel)
for Laravel.

A Symfony site requires it beside the core:

```
composer require omniscient/omnibase omnibridge/symfony
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
This package does not require omniscient/omnibase, so nothing is circular: the
core's Symfony bundle stands on this package, never the reverse.

Moved (`Base\Bridge\Symfony\X` is now an alias of the class here, declared by the
core's `src/Resources/Aliases.php` where this package is installed; the former
service ids are aliases of the new ones):

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

The former names are removed at omnibase 4's final pass, once the sites name the
new classes.

## Tests

```
docker compose run --rm test
```

PHP 8.4, the latest Symfony: a kernel of FrameworkBundle, SecurityBundle and
OmnibridgeBundle binding each contract (one object through `Runtime` and the
container, the answers of Symfony's services), a kernel without SecurityBundle that
compiles without Access, Sessions and Tokens, and `FileLinks`.

## Licence

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
