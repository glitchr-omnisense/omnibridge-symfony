---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-27 in the package's image `omnibridge-symfony:php8.4` (PHP 8.4, Symfony 8.1.9), the
checkout's own `vendor/`, no network: 38 tests, 246 assertions, green. What each class checks is in
[Testing](testing.md#the-packages-own-suite). The `bare` run (without the suggested families) needs
Composer's network and was not run that day; the commit f643d67 states it green.

The examples of this manual run in the same image on 2026-10-27, their output pasted: the test
kernel booted with and without SecurityBundle ([Installation](installation.md)), the FileLinks
decoration and formats ([Contracts](contracts.md#filelinks)), and the two configuration trees, dumped
with `config:dump-reference` ([omniauth](omniauth.md#the-configuration-tree),
[omnimap](omnimap.md#the-configuration-tree)). For the dump, symfony/yaml (not installed in this
package's vendor) was loaded from omnibridge/laravel's vendor, the same version (8.1.9).

## Verified elsewhere, as the commits and the README state

- The contracts and omniauth's sign-in on lesringostars (Symfony), with its parity suite against
  the Laravel twin.
- The map tiles on lesringostars: WebP converted with Imagick where GD has no WebP (commit 6cdce16),
  at quality 75 (3fa1968).
- `omnibridge:static:build` on lesringostars (2026-10-24, the working copy loaded by hand): the same
  report as the plain command line but for the dev 404 page (commit 67129db).
- omniauth's provider factories through a service locator, after a compiled container (debug off)
  dropped an inlined tagged iterator (lesringostars' `prod_demo_test` kernel, Symfony 8.1.5).

## What is not verified

- Symfony 6 and 7: composer.json admits them, the suite runs on Symfony 8.1 only.
- A real identity provider: Google is simulated (`FakeProvider`) and configured by
  `GoogleFactory` in the tests; no sign-in against Google's servers in this package.
- A real passkey authenticator: the tests use omniauth/webauthn's in-memory passkeys.
- omnimap providers other than OpenStreetMap through this wiring: Google is registered, not
  exercised; MapLibre is registered and resolved as a renderer (`MapTest::testTheServices`), its
  page not drawn.
- `FileLinks::formatter()` as a decoration in an application: tested by `FileLinksTest` and run here,
  registered by no configuration of this package or omnibase.
