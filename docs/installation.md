---
title: Installation
order: 10
---

# Installation

## Requirements

- PHP 8.2 or later.
- Symfony 6, 7 or 8: the components listed in [The framework packages](index.md#the-framework-packages),
  which Composer installs with the package.
- `symfony/asset` (suggested): `RouterUrls::asset()` goes through the application's asset packages;
  without it, the path is answered as it is.
- `symfony/clock` (6.2 or later) for a PSR-20 clock in the registry; without it `Runtime::clock()`
  is omnibridge's `SystemClock`.

The optional families: `omnisense/omniauth` (with `omniauth/otp`, `omniauth/webauthn`,
`omniauth/google` for TOTP, passkeys and Google), `omnisense/omnimap` (with `omnimap/google`,
`omnimap/openstreetmap`), `omnisense/omnidrive`, `omnibridge/static`. Each is wired only when its
classes exist.

## On an omnisense/omnibase site

```sh
composer require omnisense/omnibase omnibridge/symfony
```

Nothing to register: omnibase's `BaseBundle` imports this package's `config/services.php`, runs
`ContractsPass` with omnibase's own contracts added (locator `base.contracts`), registers the
omniauth extension (`OmniauthExtension::register()`) and seeds `Omnibridge\Runtime` itself. Do not
add `OmnibridgeBundle` to `config/bundles.php` there.

## In a Symfony application without omnibase

```sh
composer require omnibridge/symfony
```

There is no Flex recipe: add the bundle by hand.

```php
// config/bundles.php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    // ...
    Omnibridge\Symfony\OmnibridgeBundle::class => ['all' => true],
];
```

`OmnibridgeBundle` (an `AbstractBundle`) has no configuration of its own (no `omnibridge:` key):

| Moment | What it does |
|---|---|
| `loadExtension()` | imports `config/services.php` (`OmnibridgeBundle::SERVICES`) |
| `build()` | adds `ContractsPass` (default list, locator `omnibridge.contracts`); registers the `omniauth` extension when `Omniauth\Omniauth` exists and the `omnimap` extension when `Omnimap\Registry` exists |
| `boot()` | `Runtime::setContainer()` with the locator `omnibridge.contracts` |
| `shutdown()` | `Runtime::setContainer(null)`, when the registry still holds this kernel's locator |

The bundle order does not matter for the contracts: the pass runs at compile time, after every
bundle has loaded. For the Twig functions, TwigBundle must be registered (it is required).

A run of the package's test kernel (`tests/Fixtures/TestKernel`: FrameworkBundle, SecurityBundle,
TwigBundle, OmnibridgeBundle), booted with and without SecurityBundle in the package's image
(PHP 8.4, Symfony 8.1.9, 2026-10-27):

```php
<?php

require 'vendor/autoload.php';

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Tokens;
use Omnibridge\Runtime;
use Omnibridge\Symfony\DependencyInjection\ContractsPass;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;

foreach ([true, false] as $security) {
    $kernel = new TestKernel('test', true, $security);
    $kernel->boot();
    $locator = $kernel->getContainer()->get(ContractsPass::SERVICE);
    echo $security ? "With SecurityBundle\n" : "Without SecurityBundle\n";
    echo '  locator: ', implode(', ', array_map(static fn ($id) => substr($id, strrpos($id, '\\') + 1), array_keys($locator->getProvidedServices()))), "\n";
    echo '  path: ', Runtime::urls()->path('page', ['slug' => 'le-fuji']), "\n";
    echo '  environment: ', Runtime::environment()->name(), ', debug ', var_export(Runtime::environment()->isDebug(), true), "\n";
    echo '  Access: ', Runtime::has(Access::class) ? get_class(Runtime::access()) : 'none', "\n";
    echo '  Tokens: ', Runtime::has(Tokens::class) ? get_class(Runtime::tokens()) : 'none', "\n";
    echo '  clock: ', get_class(Runtime::clock()), "\n";
    $kernel->shutdown();
    echo '  after shutdown, container: ', null === Runtime::getContainer() ? 'none' : 'kept', "\n";
}
```

```text
With SecurityBundle
  locator: Urls, Environment, Translations, Access, Sessions, Tokens, ClockInterface, EventDispatcherInterface
  path: /page/le-fuji
  environment: test, debug true
  Access: Omnibridge\Symfony\Contract\SecurityAccess
  Tokens: Omnibridge\Symfony\Contract\CsrfTokens
  clock: Symfony\Component\Clock\Clock
  after shutdown, container: none
Without SecurityBundle
  locator: Urls, Environment, Translations, ClockInterface, EventDispatcherInterface
  path: /page/le-fuji
  environment: test, debug true
  Access: none
  Tokens: none
  clock: Symfony\Component\Clock\Clock
  after shutdown, container: none
```

Without SecurityBundle (and with `csrf_protection: false`), `SecurityAccess`, `SecuritySessions` and
`CsrfTokens` are removed at compile time: the registry has no `Access`, `Sessions` nor `Tokens`, and
`Runtime::access()` would throw `MissingContractException`. `Locales`, `Files` and `Routes` are never
in this locator: bind them yourself ([Contracts](contracts.md#binding-what-is-not-bound-here)).

## In a service

Every contract is an alias of its adapter, so a service autowires it:

```php
use Omnibridge\Contract\Urls;

final class Mailer
{
    public function __construct(private readonly Urls $urls) {}

    public function link(string $slug): string
    {
        return $this->urls->url('article', ['slug' => $slug]);
    }
}
```

The aliases are private, as Symfony's default: a service gets them by injection, not by
`$container->get()`. `Omnibridge\Runtime` reaches them through the public locator.
