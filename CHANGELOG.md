# Changelog

## [Unreleased]

### Changed

- **The field types** (`Omnibase\Field\Type\*`, 17 of them) are no longer given Twig's `Environment`, which they did not read;
  `FileType` takes omnibase's `Base\View\AssetPaths` (optional) in its place, for `ImageType`'s thumbnail. A subclass that
  passes the constructor's arguments itself drops that one (or passes the asset paths to `FileType`).
- **`Omnibase\Form\Type\SecurityLoginTokenType`** is a `DefinitionType`: omnibase's `AccountForms::signInLink()` (its model, its guard, its
  field), its `email` option the address shown, `allow_login_token` in its view as before.
- **`SymfonyValidation`** takes the translator (service `translator`, optional): the messages of omnisense/omnibridge's
  validator (a neutral `Unique`) are said in the page's language, the `validators` domain, `{{ value }}` replaced, and an ICU
  catalogue's `{value}` by the value unquoted, as omnibase's own validators give it ("\"Marki\" est déjà utilisé.").

### Added

- **omnibase's Symfony Security classes, moved from the core** (step F5c, item 3, slice 4): `Omnibase\Security\{AccessTokenHandler,
  LoginFormAuthenticator, RescueFormAuthenticator, UserProvider, OAuthUserProvider, UserChecker, SignInGuard}`,
  `Omnibase\Security\Session\*`, `Omnibase\Security\Voter\*`, as they were. Former names aliased by the core.
- **omnibase's kernel subscribers, router, warmers, collectors and responses, moved from the core** (step F5c, item 3,
  slice 3): `Omnibase\Subscriber\*` (21 more), `Omnibase\Routing\{AdvancedRouter, AdvancedRouterInterface,
  Generator\AdvancedUrlGenerator, Matcher\AdvancedUrlMatcher, Loader\RouteTranslationsResourceLoader}`,
  `Omnibase\Cache\{Warmer\*, Clearer\CodeCachesClearer, Abstract\AbstractLocalCacheWarmer}`,
  `Omnibase\Inspector\*`, `Omnibase\Response\{PdfResponse, XmlResponse}`, as they were; the classes moved before name
  them by their new names. Former names aliased by the core.
- **omnibase's console, moved from the core** (omnisense/omnibase's plan "omnibase agnostic", step F5c, item 3, slice 2):
  `Omnibase\Console\{Application, Command, Console}` and the 30 commands `Omnibase\Console\Command\*` (cache, demo, Doctrine,
  icons, mail, notifier, time machine, translation, trash, uploader, user), as they were in the core; former names aliased by the
  core.
- **`Contract\HierarchyRoles`**: omnisense/omnibridge's `Contract\Roles` over `security.role_hierarchy`, service
  `Omnibridge\Contract\Roles`, in the contracts' locator.
- **omnibase's parameter bags, moved from the core** (step F5c, item 3): `Omnibase\Service\{HotParameterBag, ParameterBag}`
  (`HotParameterBag` implements Symfony's `ParameterBagInterface` itself) and `Omnibase\Subscriber\HotParameterBagSubscriber`,
  which calls the core's `HotParameters::warm()` and lets a missing database pass. Former names aliased by the core.
- **`Contract\StackRequests`**: omnisense/omnibridge's `Contract\Requests` over the request stack (the current request; the main
  request's session and flash bag), service `Omnibridge\Contract\Requests`, in the contracts' locator.
- **omnibase's Symfony host code, moved from the core** (omnisense/omnibase's plan "omnibase agnostic", step F5c), by the F5b rule
  (`Base\Bridge\Symfony\X` and `Base\X` are `Omnibridge\Symfony\Omnibase\X`), their former names aliased by the core
  (former-names.symfony.php): `FrameworkLocator` (the framework's services BaseTrait's deprecated accessors hand out), the kernel
  subscribers `Subscriber\{TwigSubscriber, TemplatedEmailSubjectSubscriber, AnalyticsSubscriber}`, `Cache\Warmer\WebpackCacheWarmer`.

- **`Omnibase\Form\DefinitionType`**: a form type that is a neutral `FormDefinition`, its class what Symfony asks for (its name, its block prefix,
  `FormProxy::createProcessor()`), its fields described once for every host. `DefinitionForms::populate()`: fields, actions and their options,
  rewrites (PRE_SUBMIT), a `validation_entity`'s refusals moved under the fields the definition names; `formOptions()` carries `guard`,
  `validation_groups`, `validation_entity`; callable `choices`; `Kind::REPEATED` (`RepeatedType`). The sign-up, sign-in and profile types are their
  `AccountForms` definitions. `SymfonyValidation` gets omnibase's `UniqueLookup`; `SymfonyConstraints` knows `Count`.

- **omnibase's Symfony form layer** (omnibase's plan "omnibase agnostic", step F5b; Marco's answer 4): 78 classes moved from
  the core to `Omnibridge\Symfony\Omnibase\`, each under its former path - `Field\Type\*` (39), `Form\Type\*` (17),
  `Form\Extension\*`, `Form\Transformer\*`, `Form\Traits\*`, `Form\Common\{AbstractType, FormTypeInterface}`, `Form\{FormFactory,
  FormProxy, FormProcessor}` and their interfaces -, their `Base\` names kept by the core until 6.0, and `Form\SymfonyKinds`, the
  form type of each neutral kind. New: `Omnibase\Form\DefinitionForms`, a neutral `FormDefinition` as a Symfony form.
- **`Contract\SymfonyValidation`**, omnibridge's `Validation` over symfony/validator, and `Contract\SymfonyConstraints`, the kept
  constraints as Symfony's own, bound and listed by `ContractsPass`; symfony/validator required.

### Fixed

- omnimap: `OmnimapExtension` registers omnimap/maplibre's `MapLibreFactory` when it is installed,
  beside Google's and OpenStreetMap's (`OmnimapExtension::FACTORIES`): `provider: maplibre` was
  "No maplibre factory" unless the application tagged its own service.
- README: the moved classes' former names are said removed at omnibase 4's final pass (they were
  said to be aliases still), and `FileLinks::formatter()` registered by no configuration (an
  application decorates `debug.file_link_formatter` itself); `FileLinks`' docblock likewise.

### Removed

- omniauth.yaml: `oauth.providers.<name>.class`, `options` and `fields` are no longer declared by the
  tree (with their defaults null, `[]`, `[]`): no factory read them. They are part of the
  configuration's public keys: an application that sets them keeps them, handed to the factories
  as any other extra key (`ignoreExtraKeys(false)`), so nothing it writes is refused.
