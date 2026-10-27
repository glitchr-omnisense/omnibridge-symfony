---
title: Testing
order: 60
---

# Testing

## Testing an application

Under a `KernelTestCase` or a `WebTestCase`, booting the kernel fills the registry (`OmnibridgeBundle::boot()`
or omnibase's `BaseBundle`), and a new kernel replaces the previous one's services: nothing to reset
between tests. A test that sets a contract by hand (`Runtime::set()`) takes it back after
(`Runtime::set($contract, null)` or `Runtime::reset()`), or the next tests see it.

The sign-in is tested as a visitor does it, with Symfony's browser: post the form, follow the
redirection to the second factor's page, post the code. The package's `AuthTest` does exactly that,
with an in-memory accounts store, omniauth's `RecordingSender` (the e-mails kept) and `FakeProvider`
(an identity provider simulated): copy its kernel (`tests/Fixtures/AuthKernel.php`) for an
application's own.

## The package's suite

```sh
docker compose run --rm test   # composer update, then PHPUnit
docker compose run --rm bare   # without the suggested families
```

`compose.yml`'s two services use the image `omnibridge-symfony:php8.4` (`docker/Dockerfile`: PHP
8.4-cli, Composer 2, intl, zip, and GD with JPEG and WebP for omnimap's pictures), the package
mounted at `/package` (`bare`: read-only at `/source`), a named volume `composer-cache`. `bare`
(`docker/bare.sh`) copies the package, removes omnisense/omniauth and its methods, omnisense/omnimap and
its providers, omnisense/omnidrive and league/flysystem-memory, checks that none of their classes
loads, and runs the group `without-omniauth`. With the checkout's `vendor/` installed, the suite runs
without network:

```sh
docker run --rm --network none -v "$PWD":/package omnibridge-symfony:php8.4 vendor/bin/phpunit --do-not-cache-result
```

The kernels (`tests/Fixtures`): `TestKernel` (FrameworkBundle, SecurityBundle with an in-memory user,
TwigBundle, OmnibridgeBundle; without SecurityBundle on demand; a Flysystem memory storage tagged
`memory.uploads`), `AuthKernel` (one firewall signing in through omniauth alone, every method on, the
accounts in memory, TOTP secrets sealed with a test key), `MapKernel` (omnimap on OpenStreetMap, its
tiles answered by the test, the cache on a local storage).

| Test class | What it checks |
|---|---|
| `ContractsTest` | the bundle seeds the registry with each adapter (one object through `Runtime` and the container); shutdown takes it back; Urls are the router's addresses; Translations the translator's messages; Environment the kernel's; Access the authorization checker's and Sessions sign the visitor out; the remember-me domain asked at sign-out; Tokens the CSRF manager's; without SecurityBundle the container compiles without Access, Sessions and Tokens |
| `TwigTest` | the pages' functions are the contracts; they answer as the framework's services; without security the contracts leave the functions out |
| `FileLinksTest` | the format maps the container to the host; the former syntax read as Symfony's; nothing to map; the decoration links to the host; an error page links to the host; `export()` gives the renderers built outside the container the mapping |
| `DrivesTest` | the tagged storages are the drives, by their names; a file written through the drive is in the storage |
| `AuthTest` | a password signs in; a wrong password, an unknown address and a forged token are refused; tries throttled; remember-me is Security's own; TOTP secrets sealed at rest with a key; an account with TOTP is asked its code and only its page opens; the second factor's page is no one's without a first factor; an e-mailed code sent hashed and served once; a backup code stands for the TOTP once; a trusted device skips the second factor until revoked; a sign-in link serves once and a failed one is no link; a passkey signs in without the second factor; an identity provider signs in by its subject and asks the second factor; the return address follows the host of each request; an unserved provider is no page |
| `MapTest` | the services; a visitor gets the picture the server made and its tiles (WebP, written in the public folder); an administrator captures and deletes with the page's tokens, a visitor refused |
| `WithoutOmniauthTest` (group `without-omniauth`) | without omniauth installed, nothing of `Auth\` is wired |
