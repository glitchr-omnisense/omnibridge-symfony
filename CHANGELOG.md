# Changelog

## [Unreleased]

### Fixed

- The core's folder is asked of the bundle (`Base\BaseBundle::getBundleDir()`, the core's folder under omnibase 4.x
  and 5.0 alike) by the commands that list its classes (translation:entities, :enums, :crud, icon:entities, :enums,
  base:mapping, autocomplete:entities) and by ScreenResponder's templates, in place of the folder of the bundle's
  class file, which is this package's under omnibase 5.0: translation:entities had lost the core's entities
  (lesringostars' TranslationKeysTest).
- The former ids of the moved controllers are registered (services-public.php; DemoController's beside it, in the
  demo environment): `framework.error_controller: Base\Controller\ErrorController::Main` answers again.

### Added

- omnisense/omnibase's bundle and its Symfony host code, moved here for omnibase 5.0 (plan "omnibase agnostic",
  step F6), each under `Omnibase\` with its path: `Omnibase\BaseBundle`, `Omnibase\Bundle\{AbstractBaseBundle,
  AbstractBaseExtension, AbstractBaseConfiguration}`, `Omnibase\DependencyInjection\*` (`BaseExtension`,
  `BaseConfiguration`, the compiler passes, the dumpers), the Symfony controllers `Omnibase\Controller\*` and the
  service configuration `config/omnibase/` (`services*.php`, `services/*.php`). The bundle keeps its name and its
  path, the core's `src/` (`getBundleDir()` is the core's folder: `@BaseBundle/...`, the templates, the
  translations); its extension maps the core's entities under the bundle's name (DoctrineBundle derives an
  attribute mapping from the bundle class's folder and namespace); the compiler passes are found beside it. The
  controllers' services are registered under their new names, the former ids aliased (public).
  omnibase 5.0 aliases the former names (`Base\BaseBundle`...); omnibase 4.x keeps its own classes and never
  loads these, so this release serves both majors: its existing classes still name the bundle `Base\BaseBundle`.
- `Omnibase\Routing\Loader\ControllerLoader` (`type: omnibase`): the routes of omnibase's controllers, this
  package's then the core's actions (`src/Controller/Action`, `SignatureController`), imported once by an
  application in place of `@BaseBundle/Controller`.

### Changed

- Requires omnibridge/doctrine, symfony/asset, cache, event-dispatcher, expression-language, process, var-dumper
  and workflow, which omnibase 5.0 no longer requires (they were installed with omnibase 4.x already).


### Changed

- `Omnibase\Field\Type\AssociationType`, `CollectionType`, `ArrayType` and `AttributeType` on omnisense/omnibase's neutral
  `Base\Field\Behaviour\{Association, Collection, Attribute}` (plan "omnibase agnostic", step F5d, slice 7): their defaults
  are the helpers' (`Association::DEFAULTS`, `Collection::DEFAULTS`/`ARRAY_DEFAULTS`, `Attribute::DEFAULTS`, the entries' types
  given here as Symfony types), AssociationType's normalizers (`required`, `allow_add`, `allow_delete`) and the options of its
  `_collection` ask `Association`, ArrayType's length (a pattern's `{n}`) asks `Collection::length()`, AttributeType's classes,
  the options of its select of adapters and its parts' names ask `Attribute`. The same omnibridge/laravel's renderer and
  omnibase's neutral binder use; the markup is unchanged (the back office's controls and pages compared before and after).
- `Omnibase\Field\Type\WysiwygType` and `EditorType` on omnisense/omnibase's neutral `Base\Field\Behaviour\Wysiwyg` and
  `Editor` (plan "omnibase agnostic", step F5d, slice 6): their defaults are the behaviours' options, their vars the
  behaviours' - the widget's id, Quill's settings, Editor.js's (the collaboration's room from the root form's data when
  `collab_autosave` or `collab_live` asks for it), the value with its pictures' addresses, the five addresses of the editor's
  routes through omnibase's `Base\Field\Editor\EditorJs` -, and EditorType's PRE_SUBMIT reads a document without a block as no
  text through the behaviour. The same omnibridge/laravel's renderer uses; the markup is unchanged. Their constructors keep
  their arguments (omnibase's service definitions).

- `Omnibase\Field\Type\AssociationFileType` on omnisense/omnibase's neutral `Base\Field\Behaviour\AssociationFile` (plan
  "omnibase agnostic", step F5d, slice 5): its defaults are its `DEFAULTS`, its upload field's name and options its `child()` and
  `childOptions()`, the related records' files its `files()`, `entityId` its `entries()`, and what is sent made into the related
  records its `records()` - the same omnibridge/laravel's renderer and omnibase's neutral binder use; this host still guesses
  whether it holds several and in order, links an administrator to a record's edit page, and writes the records into its
  `PersistentCollection` (its inverse side told its owner). `form_type` also takes a variant's name (`file`, `image`, `avatar`).
  The markup is unchanged.
- `FileType`'s `File` constraint on `[raw]` now limits a file to its announced ceiling: omnisense/omnibase's constraint reads the
  integer it is given as bytes (it read it as bits: a field announcing 32 MB refused a file over 4 MB).

- `Omnibase\Field\Type\FileType` and its children (`ImageType`, `AvatarType`, `AudioType`, `VideoType`) on omnisense/omnibase's
  neutral `Base\Field\Behaviour\Upload` (plan "omnibase agnostic", step F5d, slice 4): their defaults are its `DEFAULTS` and
  `VARIANTS` (each type's `VARIANT`), and their view is its `vars()` - the record's stored files, their addresses, the sizes and
  types accepted, the dropzone's settings and address, an image's thumbnail, modal and cropper -, the same omnibridge/laravel's
  renderer draws; whether a field holds several, and in order, is still guessed from this host's metadata, the thumbnail's
  path still the page's asset paths, the media and file services this host's. The submission (`[file]`, `[raw]`, `[url]`, the
  dropzone's uuids) is read as before. The markup is unchanged: the 13 forms, the 85 back-office pages and the admin's controls
  of lesringostars, before and after, the same.

### Fixed

- `Omnibase\Field\Type\SelectType` tells select2 to select a record by its id (`selected` in data-select2-options, the value of
  its entry), not the record itself, which JSON printed as an empty object (`[{}]`): form-type-select2.js's `$(field).val()`
  selected nothing and a record's picker started empty in the browser (omnibase's plan "omnibase agnostic", step F5d).
  A value or an enumeration's case is told as before. omnisense/omnibase's tests/Field/SelectTypeSelectedTest.

### Changed

- `Omnibase\Field\Type\TranslationType` asks omnisense/omnibase's `Base\Field\Behaviour\Translation` what it decided itself
  (plan "omnibase agnostic", step F5d, slice 3): the required languages among the offered ones, the languages drawn (a
  single-language field's, the record's one language with the default's fields), which part is required, each language's
  fields (`locale_options`), a callable `required` checked and called - the same object the hosts without Symfony's forms ask.
  The forms and the back office's markup are unchanged. Requires omnisense/omnibase with `Translation`.
- `Omnibase\Field\Type\SelectType` makes its remote search's payload with omnisense/omnibase's `Base\Field\Search\RemoteSearch::data()`
  (plan "omnibase agnostic", step F5d, slice 2), the neutral search its ux_autocomplete routes answer with, which omnibridge/laravel's
  FormView asks as well. The payload and the markup are unchanged.

### Added

- **The field types on omnibase's neutral behaviours** (omnisense/omnibase's plan "omnibase agnostic", step F5d, slice 1):
  `Omnibase\Field\Type\{DateTimePickerType, SlugType, BooleanType, ColorType, ColorPickerType, NumberType}` take their options,
  defaults and view vars, and the colours their values, from `Base\Field\Behaviour\*`; `SelectType` its select2 settings from
  `Select::select2()` - the objects omnibridge/laravel's renderer reads. The markup is the same. `Omnibase\Form\SymfonyKinds::COVERAGE`:
  every kind implemented but `html` (markup given whole, a renderer's), which `DefinitionForms` refuses aloud
  (`Omnibridge\Form\UnsupportedKind`), as a kind with no type: Symfony guessed a text input. `Omnibase\Form\Conformance\SymfonyHost`,
  for omnibase's field conformance suite (omnibase's harness: `tests/Field/SymfonyConformanceTest`).

### Fixed

- `DefinitionForms`: a submit or a button given as a field (not an action) crashed on the `mapped` option buttons do not have.

- **`Omnibase\Service\SymfonyBaseService`** (omnisense/omnibase's plan "omnibase agnostic", step F5c, item 3): the Symfony half of
  omnibase's BaseService, which is framework-neutral since. The container hands it out as `base.service` and
  `Base\Service\BaseService` (lazy, the same 23 arguments): the kernel and its container, the console (`exec()`), the form
  factory (`createForm()`), the profiler, Symfony's request, routes and responses (`getRequest()`, `getRoute()`, `redirect()`,
  `redirectToRoute()`, `refresh()`, `isAdmin()`, `isProfiler()`, `getRouteIndex()`), `getToken()`, `isCsrfTokenValid()` with a
  form, the session object, and Symfony's services where no contract answers.

### Changed

- **The field types** (`Omnibase\Field\Type\*`, 17 of them) are no longer given Twig's `Environment`, which they did not read;
  `FileType` takes omnibase's `Base\View\AssetPaths` (optional) in its place, for `ImageType`'s thumbnail. A subclass that
  passes the constructor's arguments itself drops that one (or passes the asset paths to `FileType`).
- **`Omnibase\Form\Type\SecurityLoginTokenType`** is a `DefinitionType`: omnibase's `AccountForms::signInLink()` (its model, its guard, its
  field), its `email` option the address shown, `allow_login_token` in its view as before.
- **`SymfonyValidation`** takes the translator (service `translator`, optional): the messages of omnisense/omnibridge's
  validator (a neutral `Unique`) are said in the page's language, the `validators` domain, `{{ value }}` replaced, and an ICU
  catalogue's `{value}` by the value unquoted, as omnibase's own validators give it ("\"Alice\" est déjà utilisé.").

### Added

- **omnibase's Bridge\Symfony, moved from the core** (step F5c, item 3, slice 5): 12 adapters (`Omnibase\{LocalizerLocales,
  MediaPictures, RouterRoutes, ServiceMedia, WysiwygTexts, FlysystemFiles, ScreenResponder, SymfonyScreenContext,
  ActionArguments, ActionResults, AuthListeners, SettingBagWarmer}`), as they were (the eight others the slice moved -
  NotificationMessages, AccountScreens, the security-user and passkey adapters - are the core's again: the core and
  omnibridge/laravel reach them under any host); `ScreenResponder::legacy()` finds the core's
  templates beside its BaseBundle. Former names aliased by the core.
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
