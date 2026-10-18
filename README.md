# omnibridge/symfony

The Symfony side of omnibase. Since omniscient/omnibase 4.0 the core requires only
what its Fondation needs - classes that load without a framework - and leaves the
framework packages to this one: FrameworkBundle, SecurityBundle, TwigBundle,
DoctrineBundle, MonologBundle, Form, Messenger, Console and the components and
bridges they stand on (the list, measured class by class: the plan "omnibase
agnostique", C7).

A Symfony site requires it beside the core:

```
composer require omniscient/omnibase omnibridge/symfony
```

A Laravel site does not: it requires omnibridge/laravel instead.

Nothing else yet: no code, only the requirements. The Symfony adapters of the
core's contracts (routing, translation, security, files) live in the core's
`Base\Bridge\Symfony` for now.

The bundles a site registers beyond these (API Platform, HWIOAuth, scheb/2fa,
WebAuthn, Flysystem, Nelmio, Webpack Encore, UX) stay the site's to require: the
core suggests them.

## Licence

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
