---
title: Contracts
order: 20
---

# Contracts

The adapters of omnisense/omnibridge's contracts over Symfony's services, defined in
`config/services.php` (`OmnibridgeBundle::SERVICES`). What each contract means is in the core's
manual ([Contracts](https://github.com/glitchr-omnisense/omnibridge/blob/1.x/docs/contracts.md));
this page says how Symfony answers.

## The services

| Service id (class) | Alias | Arguments | Tags |
|---|---|---|---|
| `Omnibridge\Symfony\RouterUrls` | `Omnibridge\Contract\Urls` | `router`, `assets.packages` (null when absent) | `omnibridge.adapter` |
| `Omnibridge\Symfony\TranslatorTranslations` | `Omnibridge\Contract\Translations` | `translator` | `omnibridge.adapter` |
| `Omnibridge\Symfony\Contract\KernelEnvironment` | `Omnibridge\Contract\Environment` | `%kernel.environment%`, `%kernel.debug%`, `%kernel.secret%` | `omnibridge.adapter` |
| `Omnibridge\Symfony\Contract\SecurityAccess` | `Omnibridge\Contract\Access` | `security.authorization_checker`, `security.token_storage` | `omnibridge.adapter` |
| `Omnibridge\Symfony\Contract\SecuritySessions` | `Omnibridge\Contract\Sessions` | `security.token_storage` | `omnibridge.adapter` |
| `Omnibridge\Symfony\Contract\CsrfTokens` | `Omnibridge\Contract\Tokens` | `security.csrf.token_manager` | `omnibridge.adapter` |
| `Omnibridge\Twig\AccessExtension` | - | `Omnibridge\Contract\Access`, `Omnibridge\Contract\Tokens` (each null when absent) | `twig.extension` |
| `Omnidrive\Flysystem\OperatorDrives` | `Omnidrive\Drives` (public) | a locator of the services tagged `flysystem.storage`, by their `storage` attribute | - (only when omnidrive is installed) |
| `Omnibridge\Symfony\Static\BuildCommand` | - | `kernel`, `router` | `console.command` (only when omnibridge/static is installed) |
| `omnibridge.contracts` (`ServiceLocator`, public) | - | the contracts found, by interface name | `container.service_locator` (made by `ContractsPass`) |

An application rebinds a contract by aliasing its interface again (`services.yaml`:
`Omnibridge\Contract\Urls: '@App\MyUrls'`): the locator takes whatever service the name leads to.

## The adapters

### RouterUrls

`path()` is the router's `generate()` with `ABSOLUTE_PATH`, `url()` with `ABSOLUTE_URL` - the same
strings Twig's `path()` and `url()` print. `asset()` is `Packages::getUrl()`, the path unchanged when
symfony/asset is not installed. Beyond the contract, `generate(string $name, array $parameters = [],
int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)` gives any of the router's reference types
(a relative path, a network path): omnibase's `Base\Contract\Link::to()` asks it. An unknown route is
the router's `RouteNotFoundException`. On an omnibase site the router argument is omnibase's
`advanced_router`.

### TranslatorTranslations

`trans()` is the `translator`'s `trans()` with the same four arguments - what Twig's `|trans` answers.
`exists()`:

1. the translator's own `transExists($id, $domain, $locale)` when it has one (omnibase's
   translator: the locale's catalogue and its fallbacks);
2. otherwise, when the translator is a `TranslatorBagInterface` (Symfony's is), its catalogue of the
   locale: `getCatalogue($locale)->has($id, $domain ?? 'messages')`, which includes the fallback
   catalogues. An id written `@domain.key` is read as the key `key` of the domain `domain`;
3. otherwise false.

### KernelEnvironment

The three kernel parameters, read once at compile time. `secret()` is `kernel.secret`
(`framework.secret`), marked `#[\SensitiveParameter]` in the constructor so a stack trace does not
print it.

### SecurityAccess

`isGranted()` is the authorization checker's (the role hierarchy, the application's voters, the
access decision strategy); `getUser()` the token storage's token's user, null without a token.

### SecuritySessions

`signOut($user)` answers false when no token is stored or when its user is not the very object given
(`!==`). Otherwise it empties the token storage and expires the `REMEMBERME` cookie twice with PHP's
`setcookie()`: once with no path nor domain, once on `/` and the domain the constructor's closure
answers (omnibase gives its router's domain; empty without a closure). The cookies are sent with the
response's headers, outside Symfony's `Response`.

### CsrfTokens

`csrf($intent)` is the token manager's `getToken($intent)->getValue()` - what Twig's `csrf_token()`
prints; `isValid($intent, $token)` is `isTokenValid(new CsrfToken($intent, $token))`, false for a
null or empty token without asking.

## ContractsPass

`Omnibridge\Symfony\DependencyInjection\ContractsPass` builds the locator the registry reads. Not
final: an application's bundle runs it with its own list.

```php
new ContractsPass(
    contracts: ContractsPass::CONTRACTS,   // the names the locator answers
    services: [],                          // a contract => the service id it is bound to, when not its own name
    former: [],                            // a former name => the contract it is listed as (the same service)
    id: ContractsPass::SERVICE,            // the locator's id, 'omnibridge.contracts'
);
```

| Constant | Value |
|---|---|
| `SERVICE` | `omnibridge.contracts` |
| `ADAPTER` | `omnibridge.adapter` |
| `CONTRACTS` | `Urls`, `Routes`, `Environment`, `Translations`, `Access`, `Sessions`, `Locales`, `Files`, `Tokens` (all `Omnibridge\Contract\`), `Psr\Clock\ClockInterface`, `Psr\EventDispatcher\EventDispatcherInterface` |

What `process()` does:

1. Each service tagged `omnibridge.adapter` whose constructor needs a service that does not exist
   (a `Reference` that must be valid, in its arguments or in an inline definition among them) is
   removed, with every alias that leads to it, through other aliases too. So `SecurityAccess` goes
   without SecurityBundle, `CsrfTokens` without `framework.csrf_protection`.
2. Each contract of the list whose id (its own name, or the one `services` gives) exists in the
   container is put in the locator. `Routes`, `Locales` and `Files` are in the list but bound by
   nobody here: they appear when the application (or omnibase) binds them. The clock appears when
   symfony/clock aliases `Psr\Clock\ClockInterface` (FrameworkBundle does when the component is
   installed); the dispatcher when `Psr\EventDispatcher\EventDispatcherInterface` is aliased
   (FrameworkBundle does).
3. Each former name is added to the locator with its contract's service.
4. The locator is registered public, tagged `container.service_locator`: nothing is constructed
   before a contract is asked.

omnibase's `BaseBundle` runs it with omnibase's list (its `Nouns`, `Media`, `Pictures`, `Texts`,
the parameters, the mailer added) and the id `base.contracts`.

## Binding what is not bound here

`Locales`, `Files` and `Routes` depend on how an application names its languages, its storages and
its routes. Without omnibase, bind them yourself; the locator picks them up by their names:

```php
// src/Bridge/RequestLocales.php
namespace App\Bridge;

use Omnibridge\Contract\Locales;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestLocales implements Locales
{
    public function __construct(private readonly RequestStack $requests) {}

    public function getLocale(): string
    {
        return str_replace('_', '-', $this->requests->getCurrentRequest()?->getLocale() ?? 'fr-FR');
    }

    public function getDefaultLocale(): string { return 'fr-FR'; }

    public function getAvailableLocales(): array { return ['fr-FR', 'en-GB']; }
}
```

```yaml
# config/services.yaml
services:
    App\Bridge\RequestLocales: ~
    Omnibridge\Contract\Locales: '@App\Bridge\RequestLocales'
```

## The Twig functions

`Omnibridge\Twig\AccessExtension` (omnibridge/twig) is registered with the contracts it finds:

| Function | Answered by | Left out when |
|---|---|---|
| `is_granted(attribute, subject = null)` | `Access::isGranted()` | no `Access` (no SecurityBundle) |
| `csrf_token(intent)` | `Tokens::csrf()` | no `Tokens` (no CSRF protection) |

TwigBundle registers twig-bridge's extensions first, so these two functions replace twig-bridge's
`is_granted` and `csrf_token` of the same names, answered by the same Symfony services: a template
behaves as it did, and asks the same code it asks under Laravel. `path()`, `url()`, `asset()` and
`|trans` stay twig-bridge's: omnibridge/twig's `ContractExtension` is not registered under Symfony.
The map functions are registered with omnimap ([Maps](omnimap.md#the-twig-functions)).

## FileLinks

`Omnibridge\Symfony\FileLinks` makes the links of the error pages and the profiler open the file on
the developer's machine when the site runs in a container: `APP_PATH` (the project's path on the host,
which a site's Makefile writes in `.env.local`) against `kernel.project_dir` (its path in the
container), with Symfony's own means - `FileLinkFormatter` reads a mapping after the format,
`vscode://file/%f:%l&/srv/app/>/Users/me/site/`.

| Static method | What it does |
|---|---|
| `format(?string $ide, string $projectDir, ?string $hostDir): ?string` | the link format with the container-to-host mapping appended; `$ide` unchanged (null for none) when there is nothing to map (no host path, the same path, or a format that already maps). A name of `ErrorRendererInterface::IDE_LINK_FORMATS` (`vscode`, `phpstorm`...) is expanded first; no format at all is `FileLinks::DEFAULT_FORMAT`, `vscode://file/%f:%l`. The former syntax `vscode://file/%f[%b:/host/path]:%l` is read as the mapping |
| `formatter(?FileLinkFormatter $inner, ?string $ide, string $projectDir, ?string $hostDir, ?RequestStack $requestStack = null): ?FileLinkFormatter` | the factory of a decoration of `debug.file_link_formatter`: a `FileLinkFormatter` on the mapped format (with the profiler's `/_profiler/open?file=%f&line=%l#line%l` for a file without a format), or `$inner` when nothing maps |
| `export(?string $projectDir = null): void` | sets `$_SERVER['SYMFONY_IDE']` and `$_ENV['SYMFONY_IDE']` to the mapped format when `APP_PATH` is set and `SYMFONY_IDE` is not; the project directory defaults to Composer's root package's install path |

FrameworkBundle's `framework.ide` defaults, in debug, to `%env(default::SYMFONY_IDE)%`: `export()`
called before the kernel boots maps every link Symfony makes, including ErrorHandler's renderer for an
error before the container is built. omnibase's `BaseBundle` calls `FileLinks::export()` in its
constructor. `OmnibridgeBundle` calls neither method and registers no decoration: without omnibase,
call `export()` early (in `public/index.php` and `bin/console`, after the autoloader), or decorate
the formatter:

```yaml
# config/services.yaml (dev)
services:
    app.file_link_formatter:
        class: Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter
        decorates: debug.file_link_formatter
        factory: ['Omnibridge\Symfony\FileLinks', 'formatter']
        arguments: ['@.inner', '%env(default::SYMFONY_IDE)%', '%kernel.project_dir%', '%env(default::APP_PATH)%', '@request_stack']
```

Run in the package's image (a dev kernel with FrameworkBundle, TwigBundle and OmnibridgeBundle,
`kernel.project_dir` `/srv/app`, `APP_PATH=/Users/me/site`, 2026-10-27), the decorated formatter and
`format()`:

```php
$kernel->getContainer()->get('app.file_link_formatter')->format('/srv/app/src/Kernel.php', 12);
FileLinks::format(null, '/srv/app', '/Users/me/site');
FileLinks::format('phpstorm', '/srv/app', '/Users/me/site');
FileLinks::format('vscode://file/%f[%b:/Users/me/other]:%l', '/srv/app', null);
```

```text
vscode://file//Users/me/site/src/Kernel.php:12
vscode://file/%f:%l&/srv/app/>/Users/me/site/
phpstorm://open?file=%f&line=%l&/srv/app/>/Users/me/site/
vscode://file/%f:%l&/srv/app/>/Users/me/other/
```
