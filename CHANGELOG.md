# Changelog

## [Unreleased]

### Fixed: what stayed on Doctrine with `base.persistence: eloquent` (omnisense/omnibase's plan "omnibase agnostic", step F7d-3)

- The declarations name the core's repository interfaces: the access token handler (`User\Tokens`), the media
  controller (`Layout\ImageCrops`), the user provider and the analytics subscriber (`Users`), instead of Doctrine's
  repository classes - on Doctrine the same services.
- `DemoController` reads the account through `Base\Repository\Users` (the persistence chosen; Security's user loader),
  not `App\Repository\UserRepository`: on Eloquent, the account signed in is the one its connection is written for.
- `BaseBundle::shutdown()` lets omnibridge/eloquent's connection go of the PDO it shares with Doctrine (the service
  `omnibridge.eloquent.connection`, now public): a suite booting kernel after kernel kept one database connection per
  kernel open (MySQL's "Too many connections").

### Added: the persistence chosen, Eloquent's when it is the one (omnisense/omnibase's plan "omnibase agnostic", step F7d)

- `Omnibase\DependencyInjection\Persistence`: omnibase's option `base.persistence` (config/packages/base.yaml, an
  `%env()%` resolved when the container is compiled), chosen by omnisense/omnibridge's `Persistences` - the only one
  installed, else the one named; omnibridge/doctrine and omnibridge/eloquent both installed and none named refused
  aloud. The parameter `base.persistence` is the name chosen. On `eloquent`: omnibridge/eloquent's persistence
  (`Omnibase\Persistence\EloquentHost`: Doctrine's PDO where DoctrineBundle is installed beside, else Capsule on
  DATABASE_URL; the entities DoctrineBundle maps; omnibase's lifecycle from a service locator of the host's services
  and the entity dispatchers tagged base.entity_dispatcher; the trash and vault scopes; kernel.reset) behind
  `UnitOfWork`, `Snapshots`, `Backend`, the deprecated union `EntityManagerInterface|UnitOfWork`, `Repositories` and
  the core's repository interfaces. Doctrine's wiring is unchanged.
- `Compiler\Pass\EloquentPersistencePass`: on Eloquent, an `entity` user provider of security.yaml for the
  application's users (no `property`, no `manager_name`) is the users' repository on Eloquent (it answers Security's
  loader and provider methods as Doctrine's does).
- What still names Doctrine's services by their ids (doctrine.orm.entity_manager, a repository by its class, the
  AttributeReader) stays on Doctrine: listed by omnibase's plan, step F7d.

### Added: `omnibridge:schema:create` (omnisense/omnibase's plan "omnibase agnostic", step F7a)

- `Omnibase\Console\Command\SchemaCreateCommand` (an adapter, within AdapterSizeTest's threshold) and
  `Omnibase\Console\InstalledEntities` (where Doctrine's configuration says the entities are: the attribute drivers'
  paths, the targets resolved, the enumerations DBAL has), registered with omnibase 5.0: the installed entities' tables
  created in the empty database from their description (omnibase's `EntitySchema`, omnisense/omnibridge's
  `SchemaCreator`), no SchemaTool; `--dump-sql`; a database holding a table of the description refused. On lesringostars
  (MySQL, 132 tables): information_schema equal to the database doctrine:schema:create makes, column for column; the
  core's SchemaCreateCommandTest runs it in the harness and in lesringostars' suite.

### Changed: the other controllers answer the core's rules (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 5b)

- `Omnibase\Controller\Client\{UserSettingsController, NotificationController, ThreadSearchController,
  UserSearchController, ContactController}`, `Api\ThreadController`, `UX\{AutovalidateController,
  AutocompleteController, MediaController}`, `ProfilerController`, `ErrorController`, `WidgetController`,
  `ShortLinkController`, `SitemapController`, `MainController` and `LocalizerController::Timezone()` keep their routes
  and names and ask omnibase's rules - `Base\Auth\Account\{SecondFactor, Devices, MemberSearch}`,
  `Base\Thread\{ThreadActions, ThreadSearch}`, `Base\Notifier\{NotificationCenter, ContactMessage, TestRecipient}`,
  `Base\Request\{TimezoneSwitch, ErrorPage, ShortLinkRedirect, AttachmentDownload, SitemapPage}`, `Base\Screen\Welcome`,
  `Base\Field\Validation\Autovalidate`, `Base\Field\Search\ExchangeRate`; what stays here is Symfony's: the forms,
  scheb's TOTP authenticator, the error renderer and Twig's loader, the profiler, the responses. Their constructors (and
  config/omnibase/services/{core,media}.php) take the rules; ThreadSearchController keeps its three former arguments
  for an application's controller that extends it. Lines of code: UserSettingsController 228 -> 92,
  Api\ThreadController 179 -> 56, NotificationController 121 -> 66, MediaController 119 -> 89, AutocompleteController
  116 -> 72, ThreadSearchController 72 -> 31, ErrorController 64 -> 51, LocalizerController 81 -> 60 (the others were
  already below the threshold: SitemapController 15, MainController 17, ShortLinkController 20, WidgetController 22,
  ProfilerController 24, UserSearchController 28, AutovalidateController 29, ContactController 31).
- `/timezone/{_timezone}` (LocalizerController::Timezone()) works: a zone PHP knows kept in LOCATION/TIMEZONE and as
  a member's own, back to the referring page; its route takes a zone's slash (`requirements: _timezone: .+`), and
  `/timezone` alone forgets the choice. It read a `_locale` its path never carried and only went to the index.
- AutovalidateController is wired (it never was: /ux/validation answered 500 whatever it was sent). A short link no
  longer answers 500 (the router method it called does not exist).
- tests/AdapterSizeTest: `CONTROLLERS_STILL_ABOVE` lists eight (twelve before), each with its reason - glue of many
  routes on the core's rules.

### Changed: the account's controllers answer the core's rules (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 5a)

- `Omnibase\Controller\{SecurityController, LocalizerController, RescueController, DemoController}` and
  `Client\UserProfileController` keep their routes (the routes table is the same) and ask omnibase's
  `Base\Auth\Account\*`, `Base\Demo\DemoSignIn` and `Base\Request\LocaleSwitch`; what stays here is Symfony's: the
  forms, the firewall's sign-out and its token, the authenticator, Twig's loader. Their constructors take the rules
  (SecurityController: the seven account rules, the router, the form proxy, the parameters, the referrer;
  RescueController: the router, Twig, the form proxy, RescuePage; LocalizerController: LocaleSwitch added;
  UserProfileController: ProfileAccess added). SecurityController: 509 lines of code -> 267, LocalizerController 103 -> 81,
  RescueController 84 -> 60, UserProfileController 88 -> 72, DemoController 46 -> 33.
- `Omnibase\Response\AnswersOutcomes`: a rule's `Base\Auth\Account\Outcome` answered by a controller as it answered
  before (redirectToRoute, redirect, the index by its route or its address, the referrer, render, Security's refusal, a
  404; the account signed in first through the controller's authenticator).
- tests/AdapterSizeTest scans `Omnibase\Controller` too: `CONTROLLERS_STILL_ABOVE` lists the twelve still above an
  adapter's size, each with its reason (slice 5b's, or glue of many routes).
- No `Legacy\` copy: under omnibase 4.x the core's own `Base\Controller\*` answer (these are read by a 5.x core only).

### Changed: one pass compiles what the core declares is discovered (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 4c)

- `Omnibase\DependencyInjection\Compiler\DiscoveryPass`: the core's registries (`Base\Discovery`) compiled - `collect`,
  `contracts` (through the generic `DependencyInjection\ContractsPass`), `fallbacks`, `preferred`, `arguments`, `texts`,
  `storages` at priority 0, `enumerations` at 10 (before DoctrineBundle's middleware pass), `repositories` at -10 (after
  its repository pass) - and `DiscoveryPass::autoconfigure()`, the `autoconfigure` registry, from BaseExtension::load()
  (the Twig group's tag renderers' included). BaseExtension's prepend takes the enumerations from
  `Base\Discovery::enumerations()`; BaseBundle::boot() the contracts' locator id from the registry.
- The fifteen passes that held those lists (AdminUrlGenerator, ApplicationTexts, Attribute, Contracts, EntityExtension,
  EnumerationTypes, FlysystemStorages, Guard, IconProvider, ObfuscatorCompression, RepositoryInterfaces, Sharing,
  TagRenderer, TextOverrideCache, Trading) are `Omnibase\Legacy\DependencyInjection\Compiler\Pass\*`, and BaseExtension's
  former autoconfiguration `Omnibase\Legacy\DependencyInjection\Autoconfiguration`: registered for a core without
  `Base\Discovery` (4.x) only. Left in `Compiler\Pass`: Symfony's own - TwigComponent, DoctrineConfiguration,
  WebauthnMapping, WebauthnDeprecatedAlias, Workflow, FormerFormTypes.
- On lesringostars the compiled container is identical before and after (debug:container JSON dev, prod and hidden,
  routes, `config:dump-reference base`, `debug:config base`, the dumped XML of 4 598 services and its parameters in their
  order). tests/DiscoveryPassTest.

### Changed: omnibase's options are compiled from the core's declaration (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 4b)

- `Options\SchemaTree::build()`: a schema of options as data (omnisense/omnibridge's `Options\Processor` format) compiled
  into a Config TreeBuilder, each node with the builder calls a hand-written tree makes. Under omnibase 5.0,
  `Omnibase\DependencyInjection\BaseConfiguration` is the core's `Base\Options` compiled so; under a core without it,
  the former hand-written tree, now `Omnibase\Legacy\DependencyInjection\BaseConfiguration`. On lesringostars
  `config:dump-reference base`, `debug:config base` (dev, prod) and the compiled container are identical before and after.
- `Declarations::environment()`: the `env(NAME)` parameters a group's services need (the core's `Options::environment()`:
  `COLLAB_TICKET_SECRET`, `COLLAB_RELAY_WS_URL`, `BASE_TRADING_LIVE`), set where `collab` and `media` are compiled, in
  place of the literals those files wrote.
- tests/SchemaTreeTest: the tree and the Processor give the same values, refusals and deprecations (skipped with an
  omnisense/omnibridge older than its `Options\`); omnibase's own options are compared in the core's harness.

### Changed: omnibase's services are compiled from the core's declaration (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 4a)

- Under omnibase 5.0, `Omnibase\DependencyInjection\Declarations::group()` compiles a group of `Base\Services` (the
  core's 192 definitions, with their tags, calls, decorations, parents, bindings, contracts and `symfony` parts) into the
  container; `config/omnibase/services-list.php` and each `services/<group>.php` call it in the group's place among this
  bridge's own definitions, which they keep (controllers, subscribers, voters, commands, warmers, form types, the
  Doctrine and Twig bridges' services, Symfony's fixes). `services-list-contracts.php` is gone (the contracts are the
  declaration's). lesringostars' compiled container is identical before and after (dev and prod: every service, its
  arguments, calls and tags, the aliases; `usages`, maker's suggestions and the order between events' listeners compared
  as sets). Under omnibase 4.x nothing here is read (the 4.x core loads its own configuration).

### Changed: the console's other commands are adapters (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3b)

- Under omnibase 5.0, `Omnibase\Console\Command\{AutocompleteEntities, BaseMapping, EntityDiscriminator, IconEntities,
  IconEnums, MailTrap, Notifier, NotifierMailTest, ThreadPublishable, TimeMachineSnapshot, TimeMachineSnapshotBackup,
  TimeMachineSnapshotRestore, TimeMachineStorage, TrashPurge, UserCreate, UserNotification}Command` run the core's
  `Base\Console\Task\*`, and `Doctrine{ArrayUpgrade, DatabaseImport, SchemaCharset}Command` omnibridge/doctrine's
  `Omnibridge\Doctrine\Omnibase\Console\Task\*`, printing the Report; same names, options, constructors and
  services. What is the host's stays here: the questions (ConfirmationQuestion, Question, ChoiceQuestion), the import's
  progress bar, the TimeMachine's console output, mailer.transports for a release. The application's `src/` is
  kernel.project_dir's (the former code read `./src` from the working directory).
- Under omnibase 4.x each is its former code in `Omnibase\Legacy\Console\Command\` (UserCreate's, MailTrap's and
  TimeMachineStorage's private properties protected there).
- tests/AdapterSizeTest: no Console class left in STILL_ABOVE; user:create's token is PasswordChangeSubscriber's.

### Changed: the console commands are adapters (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 3a)

- Under omnibase 5.0, `Omnibase\Console\Command\{TranslationCrud, TranslationEntities, TranslationEnums,
  TranslationControllers, TranslationSettings, CacheClear, CacheClearSessions, DemoReset, UploaderEntities,
  UploaderImages, UploaderImagesCrop}Command` turn their options into the input of the core's `Base\Console\Task\*`
  and print its `Base\Console\Report` (`Omnibase\Console\Reports`, as it comes); same names, options, constructors
  and services. demo:reset runs the console's commands through `Omnibase\Console\ApplicationCommands`; cache:clear
  gives the task FrameworkBundle's clear, the second clear's process and the sessions' command;
  uploader:images:crop asks the resolution and the confirmation itself. Under omnibase 4.x each is its former code,
  in `Omnibase\Legacy\Console\Command\` (DemoResetCommand's properties protected there).
- translation:controllers reads the kernel's router (its former code read one it was never given).
- `tests/AdapterSizeTest`: the ten of them that were above an adapter's size left STILL_ABOVE (11 left, the
  Console's other commands: slice 3b).

### Changed: the subscribers are adapters (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 2)

- Under omnibase 5.0, `Omnibase\Subscriber\SecuritySubscriber` asks the core's request rules for the rest of its
  events: `Base\Request\Rule\Accounts` (the impersonation's reminder, the kick, the presence, the verification's
  notice, the approval; before the page and on the answer), `MaintenancePage`, `LaunchPage` - each decision
  (`Base\Request\Decision`) applied here: notices, the address kept, the sign-out, the profiler, the answer. Its
  service takes `Omnibridge\Persistence\UnitOfWork` and `Omnibridge\Contract\Urls` too.
- `LocalizerSubscriber` asks `Locale` (the page's language, LANG, the time zone), `IntegritySubscriber` asks
  `Integrity` (still registered by nothing), `AnalyticsSubscriber` asks `Analytics`; `LoginFormAuthenticator::start()`
  asks `EntryPoint`. Under omnibase 4.x each is its former code, in `Omnibase\Legacy\Subscriber\`.
- `tests/AdapterSizeTest`: AnalyticsSubscriber left the classes above an adapter's size.

### Changed: the voters and SecuritySubscriber are adapters (omnisense/omnibase's plan "omnibase agnostic", step F5e, slice 1)

- Under omnibase 5.0, `Omnibase\Security\Voter\{AccessVoter, AdminVoter, PermissionVoter, ComplaintVoter}` ask the core's
  `Base\Auth\Policy\{Access, Maintenance, Launch, Admin, Permission, Complaint}` (same constructors, constants, services
  and decisions), and `Omnibase\Subscriber\SecuritySubscriber` its `Restriction` (onAccessRequest) and `Welcome`
  (onLoginSuccess); its other rules are the former ones until the next slice. Under omnibase 4.x, which has no
  policies, each name is the former class, kept in `Omnibase\Legacy\` until 4.x is no longer served.
- `tests/AdapterSizeTest`: no class of `Omnibase\{Security, Subscriber, Console\Command}` grows above an adapter's size
  (60 lines of code) but those recorded (the next slices'), and the voters import no entity of omnibase's.

### Added

- `Base\Validator\Constraints\UniqueValidator` registered on the unit of work where the core has it (omnibase 5.0,
  step E1: UniqueEntity without Doctrine, on `Redirection` and `TextOverride`).

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
