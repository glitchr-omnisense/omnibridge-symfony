# Changelog

## [Unreleased]

## [2.0.0] - 2026-11-14

For omnisense/omnibase 6.0, which removes the former names of the classes it moved here (`conflict`:
omnisense/omnibase < 6.0; the core 6.0 conflicts with this package < 2.0). The core's UPGRADE-6.0.md lists them.

### Removed: the former ids and the former form type names
- Every service is registered under its class's name only. The former ids (`Base\Security\UserChecker`,
  `Base\Form\FormProxy`, `Base\Routing\AdvancedRouterInterface`, the core's repositories `Base\Repository\X`, the
  controllers' `Base\Controller\X`...) are gone, with `FormerServiceIdsPass` (1.1.2) and `FormerFormTypesPass` (Symfony
  Form knew a moved type under both names). The controllers are public services under their names.
- `SymfonyKinds::MAP` gives the new names (`Omnibridge\Symfony\Omnibase\Field\Type\*`).
- `Form\FormTypes::same()`, `in()`, `declared()` compare names as written, a leading backslash aside: no former name
  (they no longer read the core's `FormerNames`).
- The union aliases `Doctrine\ORM\EntityManagerInterface|Omnibridge\Persistence\UnitOfWork` and
  `Omnibridge\Contract\Views|Twig\Environment` (the core 6.0's constructors take the contract only);
  `ScreenResponder` takes `Views`, `UserChecker` and the former `UserCreateCommand` a `UnitOfWork`.
- `Legacy\Subscriber\SecuritySubscriber::onMaintenanceRequest()`/`onLaunchRequest()` (they called the core's
  `redirectOnDeny()`, gone at 6.0; the 5.x subscriber applies the rules itself) and the former cache:clear's body
  in `Legacy\Console\Command\CacheClearCommand` (it used the core's `CacheClearTrait`; the command runs the core's
  `CacheClear` task).

### Changed
- BaseBundle maps `App\Repository\X` from `Omnibridge\Doctrine\Omnibase\Repository\X` and `App\Form\X` from
  `Omnibridge\Symfony\Omnibase\Form\X` (by their former names before), and registers the core's repositories under
  their names.
- Every former name in config/, src/, tests/ and docs/ written with its new name. composer.json: branch alias
  `dev-2.x` → `2.x-dev`.

### Kept
- `Legacy\` (the parents of the commands, subscribers and voters) and `Console\Command`'s protected `$entityManager`.

## [1.1.2] - 2026-11-14

### Fixed: a moved form type works under its former name too
- `Omnibase\Form\FormTypes` (new, internal to omnibase's part) compares a form type named in a string with a
  class under either of its names. It is read from the core's alias map (`Base\Database\FormerNames`, the core's
  `former-names.symfony.php`), not from a list of its own. `Base\Field\Type\ImageType` and
  `Omnibridge\Symfony\Omnibase\Field\Type\ImageType` are the same type there.
- `LayoutSettingListType` (omnibase/admin's settings pages): since omnisense/omnibase 5.0, a setting declared with
  a former name (`Base\Field\Type\ImageType`, `FileType`, `AvatarType`, `DateTimePickerType`) lost its handling.
  The upload options (`max_size`, `mime_types`, `empty_data`: its own stored value on a plain Save) and the stored
  upload kept on a plain Save were missing. A date picker's stored string was not made a date, so the form failed
  with "Expected a \DateTimeInterface". Both names now get the same options and submit the same data.
- `LayoutSettingListType`: a date setting that already held a date (the SettingIntl the bag holds, converted by a
  form built before in the same request) was set to `null`. It is kept now, and so is any `\DateTimeInterface`.
- `AssociationType` compares a field's `form_type` with `AssociationType` and `CollectionType` under either name.
- A service registered under a moved class's former id also answers to its new class name. Until now,
  `Base\Bridge\Symfony\ScreenResponder` was the only id, so a constructor typed on
  `Omnibridge\Symfony\Omnibase\ScreenResponder` was autowired `null`. The new
  `DependencyInjection\Compiler\Pass\FormerServiceIdsPass` aliases the new name to the former id: one service,
  public where the former id is, and the former id unchanged. It covers 214 ids in the omnibase harness:
  the field and form types, the console commands, the subscribers, the cache warmers, the security services,
  `Base\Bridge\Symfony\*`, the router, and omnibridge/doctrine's moved repositories. A new name the container
  already defines is left alone.

### Unchanged
- `SymfonyKinds` still gives a kind's type by its former name (`Base\Field\Type\AssociationType`). Callers that
  compare a descriptor's `getFormType()` with a former name rely on it, and so does the core's own `KindsTest`
  (omnibase/admin 1.0.0 among those callers). It moves to the new names with omnisense/omnibase 6.0, when the
  former names go.

### Tests
- `tests/Omnibase` needs the core and a host kernel, so it runs in the omnibase harness (this checkout mounted as
  compose.checkouts.yml does) and is skipped by this package's own suite. PHPStan sets it aside, as it does
  `src/Omnibase`. `LayoutSettingListTypeTest` builds the settings form with the former and the new name and
  compares the upload options, the date conversion and the data a plain Save submits. It also builds the form
  twice on one bag (the date kept). `FormerServiceIdsTest`: both names give the same instance, the
  ScreenResponder is public under its new name, and every moved id the container keeps has its new name.
- `Static\BuildCommandTest` holds with omnibridge/static 1.0 and 1.1. Since 1.1, the first export into an empty
  cache reads `full (no record of an earlier export in the cache)` instead of `incremental`. The command is
  unchanged: the same two pages are rendered, and the later builds read as before. A site built in CI from a
  fresh `var/cache` gets a full export each time unless its `static.php` calls
  `Site::ignoreLoaded(<kernel cache dir>/*)`. That export is correct, only slower (omnibridge/static's
  docs/incremental.md).

## [1.1.1] - 2026-11-13

### Changed
- The help text of `base.local_business.extra` gives a neutral example organisation.

## [1.1.0] - 2026-11-13

### Added: `omnibridge:static:build --cache`, `--only` and `--full`
- `Static\BuildCommand` hands omnibridge/static's command line its incremental export options. `--cache=<folder>`
  keeps the renderer's answers between builds (`Site::cache()`), so a page whose declared inputs did not change
  (`Site::dependsOn()`, the site's file among them) is not rendered again. `--only=<pattern>,...` renders the
  addresses it names whatever the cache holds (a targeted rebuild). `--full` renders everything and rewrites every
  file. An empty `--cache` is invalid (`INVALID`, the command line's message). `--out` takes an empty folder or an
  earlier export; only the files that changed are written there. An omnibridge/static from before its incremental
  exports ignores the three options.
- Tests: `Static\BuildCommandTest` runs the console command on a site file whose pages declare their content files.
  A second build reuses both pages, a changed file renders its page alone, `--only` renders the address it names,
  `--full` renders both, a build without `--cache` reuses nothing, and the offer the site's file receives is this
  host's.

### Changed: composer.json resolves without `minimum-stability: dev`
- The package's own `minimum-stability: dev` is gone. Each development requirement without a stable release says
  its stability itself (`1.x-dev`, `@dev`), and `omnisense/contracts` is required at `^1.0` for `Policy\RateLimiter`'s
  tests. `require` was stable already: a stable project installs this package with `omnisense/omnibridge` and
  `omnibridge/twig` 1.0.

### Fixed: a container compiled before the lazy reader and router boots as before
- With debug off a compiled container is never rebuilt, and `cache:clear` boots the one in place first: new code over
  a `var/cache/prod` compiled by 3144602 (and omnisense/omnibase 9b33abb) lacked `base.attributes.names` and the
  attribute reader in the contracts' locator, so the boot made no reader and `AttributeReader::getInstance()` answered
  none ("Call to a member function getParameterBag() on null" in `cache:clear --env=prod`); its router's options named
  Symfony's matcher and generator, and omnibase's router, no longer made at boot, was never asked. `BaseBundle::boot()`
  makes the reader where the container has no `AttributeReader::NAMES` parameter, and `advanced_router` where it has no
  `AdvancedRouterPass::PARAMETER` (`omnibridge.router.advanced`, which the pass now sets where it named both classes),
  as before those changes. The enumerations already read their folders without `omnibridge.doctrine.enumerations`.
  omnisense/omnibase's `StaleContainerTest` boots such a container.

### Changed: omnibase's router is made when routing first needs it, and the enumerations' list is compiled
- `Omnibase\BaseBundle::boot()` made `advanced_router` (about 20 ms) on every command and every request: its
  constructor set Symfony's router's `matcher_class` and `generator_class` to omnibase's matcher and generator, and
  handed them itself through their statics. `Compiler\Pass\AdvancedRouterPass` sets both options on `router.default`
  when the container is compiled; the boot hands its container to `Routing\AdvancedRouter::setContainer()` (a previous
  kernel's router forgotten, this container's taken at once where already made), and the matcher and the generator
  make the router from it when they are first made (`AdvancedRouter::current()`). Routing answers as before; a
  command or a request that matches and generates nothing no longer makes the router, Symfony's router, the
  localizer, the firewall map and the cache it is given. A kernel's shutdown lets go of its container
  (`forgetContainer()`), its router staying the matcher's and the generator's. A generator made where no container
  was handed and none was set is Symfony's, as it was before the router was made and named it.
- The boot registered the enumerations of the core's `src/Enum` and of the application's with DBAL by reading both
  folders and loading each of their classes (omnibridge/doctrine's `Types::register()`, 10 to 15 ms).
  `Compiler\Pass\EnumerationsPass` finds them once, when the container is compiled (`Types::enumerations()`), in the
  parameter `omnibridge.doctrine.enumerations`, and the boot registers that list (`Types::register()`, then
  `Types::registerEnumerations()`: the same types under the same names). Each folder is a resource of the container:
  in debug, a file added, changed or removed there rebuilds it, and the list with it. A container compiled before
  has no such parameter, and the boot reads the folders as before.

### Changed: the attribute reader is no longer made at each boot, and its list of attributes is compiled
- `Omnibase\BaseBundle::boot()` asked the container for omnisense/omnibase's `AttributeReader` so that Doctrine's SQL
  filters, which reach it through `getInstance()`, found one; making it made the router, the entity manager, the
  hydrator, the storages and the rest it is given, and its constructor read the attribute folders and loaded each of
  their classes - on every command and every request, attributes read or not. With a core whose reader is made when
  first asked (`AttributeReader::NAMES` defined: through the contracts' registry handed just before), the boot no
  longer makes it; a previous kernel's reader is forgotten, so that this container's is the one asked. The boot still
  made `advanced_router`, which handed Symfony's router omnibase's matcher and generator, as the reader's construction
  did (no longer since, see above). An older core is given its reader at boot, as before.
- `Compiler\Pass\AttributesPass`: the attributes of the folders the reader searches (the core's, `base.attributes.paths`)
  found once, when the container is compiled (`AttributeReader::discover()`), in the parameter `base.attributes.names`,
  which the reader reads instead of the folders. Each folder is a resource of the container: in debug, a file added,
  changed or removed there rebuilds it, and the list with it. Nothing is done with a core without `discover()`.

### Fixed: `cache:warmup` and `cache:clear` without debug
- Both stopped on DoctrineBundle's metadata cache warmer ("DoctrineMetadataCacheWarmer must load metadata first"):
  under omnibase the entity manager has always loaded metadata by then - the settings laid over the parameters at each
  command's start (`HotParameterBagSubscriber`), the reader's and the manipulator's own warmers when the container is
  built. `DoctrineConfigurationPass` puts `Cache\Warmer\DoctrineMetadataCacheWarmer` in its place, with its arguments:
  where nothing is loaded it does what DoctrineBundle's does; where something is, a metadata factory of its own over
  the same entity manager loads every class into the file.

### Changed: the pool's class aliases are declared when first needed, not each at boot
- `Omnibase\BaseBundle::boot()` declared every alias of its pool (`App\Entity\X` for `Base\Entity\X`, a plugin's
  `Base\Entity\Marketplace\X`...), and `class_alias()` loads the class it aliases: every class of every bundle was
  loaded on each request and each command, about a thousand files and 150 to 300 ms of a console start without
  opcache (the omnibase harness: 895 aliases, 2,668 classes declared by the boot; 804 now).
- Each alias is now declared when its class is loaded - by its name, as the parent or an interface of another, by
  the former name its file aliases - or when the alias itself is asked, through an autoloader prepended for the
  names waiting (`AbstractBaseBundle::deferAlias()`), and at once when its class is already loaded. `instanceof` and
  the types written with an alias, which PHP checks without autoloading, answer as before: an object of the class
  cannot exist before the class is loaded. The pool holds the class each alias stands for where it is not the name it
  lists (`base.alias_declared`: a former name, an alias its file declares); a pool written before it (no
  `cache:clear` since) declares its aliases at once, as before. A class whose file is included without an
  autoloader (Doctrine's attribute driver listing every entity, a second class in one file) has its alias declared
  when the alias is asked, as the convention's fallback already did.
- Tests: `DeferredAliasesTest` (a class loaded, the alias asked, a parent and an interface loaded with another
  class, a former name by either name, a class already loaded, an alias already taken, under the
  `DebugClassLoader`).

### Fixed: a member's own language is the one their account holds
- `Omnibase\Subscriber\LocalizerSubscriber` (and its 4.x `Legacy\` order) gave omnibase's `Locale` rule
  `Base\Entity\User::getLocale()`, which answers the page's language for an account that holds none (the localizer's
  current one: the site's, or in a long-lived worker the previous request's). The rule puts the member's language
  before `LANG` and the browser's, so such a member could not change the page's language. The member's language is now
  the account's stored locale, none when it is empty (`Legacy\Subscriber\LocalizerSubscriber::memberLocale()`).

### Added: `Policy\RateLimiter`, omnisense/contracts' rate limiter over Symfony's
- `Omnibridge\Symfony\Policy\RateLimiter` implements omnisense/contracts' `Policy\RateLimiterInterface` (the
  limiter a `PolicyGuard` counts a provider's usage policy with, the third argument of a family's registry) over
  symfony/rate-limiter: each limit of the policy is a `fixed_window` `RateLimiterFactory` of its own (its maximum,
  its window in seconds) over the application's `StorageInterface` (a `CacheStorage` on a shared pool: every process
  counts together) and its `LockFactory` when one is given. `allowance()` looks at the window without counting
  (`consume(0)`), `hit()` counts one call, a limit of 0 lets nothing through. A provider's wait (`block()`,
  `blocked()`: a 429's `Retry-After`, a `RateLimit` field with nothing left) is a `Policy\Block` state in the same
  storage.
- omnisense/contracts and symfony/rate-limiter are suggested and in `require-dev` (with symfony/lock), not required:
  unless both are installed, `Policy\`'s files declare nothing (`class_exists(Policy\RateLimiter::class)` is
  false, nothing fails to load). No service is registered: the container wiring comes with a family's Symfony
  wiring.
- Tests: `Policy\RateLimiterTest` (two limiters over one storage count together, an application's cache pool, a
  wait kept in the storage, a limit of 0); `Policy\WithoutPolicyPackagesTest` (in `bare` too, which now installs
  neither package).

### Changed: `Policy\RateLimiter` on omnisense/contracts' round 2 (5bac28b)
- A limit over several windows (`every`: Trakt's 500 every 5 minutes) is counted over its whole length,
  `RateLimit::seconds()` (300 seconds), where `window->seconds()` counted it per minute.
- `RateLimiter` implements `ConsumingRateLimiterInterface`: `consume($key, $limit, $amount)` counts a call's amount at
  once (a cost of 3 quota units, 1,200 characters translated) with one `consume($amount)` of Symfony's limiter, one
  round trip to the storage, where `PolicyGuard` called `hit()` once per unit. An amount over what the window has
  left (characters billed, known after the call) spends what is left; `hit()` is `consume(1)`.
- Needs omnisense/contracts at 5bac28b or later (still `^1.0@dev`, untagged): over an older checkout, without
  `ConsumingRateLimiterInterface`, `Policy\RateLimiter` declares nothing, as without the package; the bridge loads.
- Tests: `RateLimiterTest` (a 5-minute window, an amount counted in one write, a cost per operation, an amount over
  what is left), `WithoutPolicyPackagesTest` on the new interface.

## [1.0.0] - 2026-11-10

### Changed: omnibase's BaseBundle needs less of its host
- symfony/monolog-bundle moves from `require` to `suggest`: nothing of omnibase's Symfony host needs MonologBundle's
  services with omnisense/omnibase 5.0 (its SpamChecker made `monolog.http_client`'s client itself); omnibase logs
  through psr/log's `LoggerInterface`, whatever answers it. An application that registers MonologBundle requires it
  (every site and the core's harness do).
- `Omnibase\DependencyInjection\Compiler\Pass\DefaultCachePoolPass`: omnibase's cache pool `cache.adapter` (the router,
  the setting bag, the code caches) made on the application's default adapter where it configures none, as
  `framework.cache.pools.cache.adapter: ~` would; a configured pool is kept. A host without it did not compile.
- `Controller\UX\AutocompleteController` takes the exchange rates' service where there is one (the core declares it only
  with symfony/http-client's PSR-18 client); without it the forex route answers 404 instead of the container not
  compiling. Controllers' arguments take the "?" prefix of an optional service, as the subscribers' did.
- With omnisense/omnibase 5.0's own part (SpamChecker's client, the storages' defaults, the Twig loader on the folders
  there are), omnibridge/parity's Symfony host boots with FrameworkBundle, TwigBundle, SecurityBundle (one firewall),
  DoctrineBundle (the reference application's) and BaseBundle alone: no MonologBundle, league/flysystem-bundle,
  florianv/swap, symfony/http-client, cache pool, base.yaml nor empty folders. Still needed: SecurityBundle registered
  (omnibase's host is built on Symfony's security) and sessions (its forms' CSRF tokens).
- Tests: DefaultCachePoolPassTest (in `bare` too).

### Fixed: 405 Method Not Allowed for a method the route does not allow
- omnibase's matcher (`Omnibase\Routing\Matcher\AdvancedUrlMatcher`) caught every exception of Symfony's matcher and
  retried the address with a trailing slash, so a request whose path matched a route its method does not allow (GET on a
  POST-only action) ended on the retry's `ResourceNotFoundException`: a 404, where Symfony and Laravel answer 405. The
  matcher now rethrows the `MethodNotAllowedException` when the retry matches nothing, and the router listener answers
  405 with `Allow` (the route's methods). Only for a request being matched (`matchRequest()`, the router listener's
  call): `match()` asked about an address alone keeps answering "no route" for it, as its callers (the breadcrumbs, the
  localizer, which catch `ResourceNotFoundException`) expect. The trailing-slash retry, the redirections and every other
  answer are unchanged. omnibridge/parity's `method-not-allowed` difference is gone. tests/Routing/AdvancedUrlMatcherTest
  (in `bare` too): three of its seven cases fail before.

### Fixed: `use_model` finds the core's models of the moved form types (omnisense/omnibase's plan "omnibase agnostic", step S)
- omnibase's form types moved to this bridge (`Omnibridge\Symfony\Omnibase\Form\Type\*`) and their models stayed in
  the core (`Base\Form\Model\*`), but the option `use_model` named the model beside the type: a page asking ContactType
  by its new name (a site's contact page over `Controller\Client\ContactController`) answered 500, "Form model
  Omnibridge\Symfony\Omnibase\Form\Model\ContactModel not found"; likewise every type whose `getModelClass()` is
  `Form\Common\AbstractType`'s or `SecurityLoginType`'s, whatever name it was asked by. `Form\Common\FormModels::resolve()`
  (new) answers the model named beside the type where it exists, else the core's class of the same name where the type is
  this bridge's and the core has it; `getModelClass()` and the form factory's default go through it. A site's model beside
  its own type is found as before; the former names (`Base\Form\Type\*`) found the core's models already.
  tests/FormModelsTest (in `bare` too); the core's tests/Form/ContactFormModelHttpTest shows and sends the contact form by
  the new name.

### Fixed: PhpSpreadsheet's cache warmer only with phpoffice/phpspreadsheet (omnisense/omnibase's plan "omnibase agnostic", step S)
- omnibase's cache warmers are declared from `Omnibase\DependencyInjection\CacheWarmers::declarations()`, which
  declares `Base\Cache\Warmer\SpreadsheetCacheWarmer` only where phpoffice/phpspreadsheet is installed: the core only
  suggests it since 5.0, and a site without it had cache:clear (its optional warmers: a web container's start) stop on a
  ClassNotFoundError. The other warmers are declared as before. tests/OptionalPackagesTest (in `bare` too). No other
  service of the bridge is built on a package the core began suggesting at 5.0 (league/flysystem-ftp, symfony/discord-notifier,
  symfony/http-client, web-auth/webauthn-lib: none named; omnimap's PSR-18 client was already guarded).

### Changed: the form and field types read the mapping on the persistence's shapes (omnisense/omnibase's plan "omnibase agnostic", step B leftovers)

- `Form\FormMapping` (new) answers what the association, select, file, translation, attribute and slug types, the form
  extensions and FormGuessTrait asked of omnibridge/doctrine's ClassMetadataManipulator alone: the form fields a class's
  mapping suggests (`fields()`, getFields()'s answer), a property's nullability (`nullable()`, getMapping()'s and
  getFieldMapping()'s `nullable`), a to-many association's order (`orderBy()`), whether the closest entity owns a
  collection (`isCollectionOwner()`), a persisted collection's owner and sides (`collection()`, a `CollectionMapping`: what
  PersistentCollection::getMapping() and getOwner() gave). It reads the manipulator's neutral methods only (getShape(),
  getFieldName(), getTypeOfField()...), so the same code runs on Doctrine and on omnibridge/eloquent; the core's
  FormMappingTest compares its answers with Doctrine's manipulator's for every class Doctrine maps, on Doctrine's shapes and
  on the Description. getClassMetadata() in the types became getShape(); the types call no method the core's
  MetadataManipulator lacks.
- A collection a persistence read is doctrine/collections' `AbstractLazyCollection` (`FormMapping::isPersisted()`):
  Doctrine's PersistentCollection and omnibridge/eloquent's LazyCollection. Its owner and property are the collection's
  own where it knows them (Doctrine's), else the holding form's (its parent's data, its property path), its sides the
  owner's shape. On eloquent the association, select, file and attribute types now keep the inverse side of a to-many
  association as on Doctrine (the entries' owning side set and unset), the translations drop an empty one, the form
  processor dehydrates such a collection, and a collection's class is guessed as its entries' (it was LazyCollection's).
- What only Doctrine has stays Doctrine's and is asked only of a Doctrine collection: the collection regions of the
  second-level cache an association's entries are evicted from (the entries themselves through the core's EntityCache,
  which omnibridge/eloquent answers with nothing to evict), and a translations collection's owner where no form holds it
  (TranslationType with `multiple`).
- getFields()'s field types are named by their classes (it imported their former names, Base\Field\Type\*). A to-many
  association's order is read as the Description writes it (`ASC`, `DESC`), where Doctrine 3's SortDirection read as
  "ASCENDING"/"DESCENDING" (guessOrderDirection()). FormGuessTrait's guessNullable() loses a statement with no effect
  (a mapping's `allow_null`, read and dropped).

### Changed: Doctrine a suggestion (omnisense/omnibase's plan "omnibase agnostic", step B3)

- composer.json: omnibridge/doctrine, doctrine/doctrine-bundle and symfony/doctrine-bridge move from `require` to
  `suggest`. A site installs omnibridge/eloquent alone: its kernel compiles, lints in dev and prod, reads and writes
  through Eloquent with omnibase's behaviours, doctrine/orm not installed (only doctrine/persistence, which the core's
  entity dispatchers take their event arguments from).
- Doctrine's wiring is declared only where it is there (`DependencyInjection\Persistence::hasDoctrine()`:
  `Persistences::isInstalled('doctrine')` and DoctrineBundle registered): its listeners and subscribers, the DBAL types,
  the naming strategy and metadata factory, Doctrine's unit of work, backend and repositories, the Doctrine-named
  commands (doctrine:schema:charset, doctrine:array:upgrade, doctrine:database:import, base:mapping,
  entity:discriminator); the types and filters registered at boot with Doctrine only. Without Doctrine,
  `base.database.metadata_manipulator` is the core's `MetadataManipulator` (on the persistence's shapes) and the
  repositories the core still names by class (`Base\Repository\Layout\TextOverrideRepository`) are Eloquent's.
- The form and field types, FormFactory, the form extensions and SymfonyBaseService are typed on
  `Omnibase\Database\MetadataManipulator`: the core's MetadataManipulator on 5.x (Doctrine's manipulator extends it),
  Doctrine's on 4.x or with an omnibridge/doctrine older than step E3. CropperType reads its fields from the entity's
  shape. `Console\Command` and the legacy commands take an optional entity manager (`getEntityManager()` refused aloud
  without Doctrine); omnibridge:schema:create runs on omnibridge/eloquent's connection without Doctrine.
  SecuritySubscriber takes the users' repository by its interface (`Base\Repository\Users`) on 5.x.
- `hasDoctrine()` looks for DoctrineBundle among the kernel's bundles (`kernel.bundles`): in an extension's `load()` the
  builder is Symfony's merge builder, which registers that extension alone, so `hasExtension('doctrine')` answered false
  on every site with Doctrine (its naming strategy then undeclared: "doctrine.orm.default_configuration has a dependency
  on a non-existent service").
- Each class typed on `Omnibase\Database\MetadataManipulator` defines the alias when its file is loaded, as BaseBundle.php
  does: a 4.x application registers the core's `Base\BaseBundle`, whose FormFactory was otherwise refused Doctrine's
  manipulator (a type check never autoloads). omnibridge:schema:create's choice of database (Doctrine's connection or
  omnibridge/eloquent's) is `Console\InstalledEntities::database()`'s: the command stays of an adapter's size.
- HotParameterBagSubscriber leaves the parameters as configured on Eloquent's `QueryException` (or a `PDOException`) as
  it does on Doctrine's: omnibridge:schema:create could not start on an empty database without Doctrine.
- What still asks Doctrine's manipulator for Doctrine's own objects (`getClassMetadata()`, `getMapping()`,
  `getEntityManager()` in the association, select, file and translation types) is reached on Doctrine only.

### Added: the packages' listeners of the writes on eloquent (omnisense/omnibase's plan "omnibase agnostic", step B2)

- On `base.persistence: eloquent`, the services tagged `omnibridge.lifecycle_listener` (omnisense/omnibridge's
  `Lifecycle\Listeners::TAG`) are handed to omnibridge/eloquent's persistence (`EloquentHost::persistence()`'s
  `$listeners`, a tagged iterator: made at the first event). glitchr/typesense-bundle tags its indexer so: the search
  index follows Eloquent's writes as it follows Doctrine's.

### Fixed: every command reads and writes through the persistence chosen (omnisense/omnibase's plan "omnibase agnostic", step B4)

- `Base\Console\Command`'s definition calls `setUnitOfWork()` with `Omnibridge\Persistence\UnitOfWork`, so every command
  declared on it gets the persistence chosen (thread:publishable alone did, F7f-3): user:notifications, notifier:mail:test,
  user:create, uploader:images:crop, entity:discriminator, icon:entities, autocomplete:entities read and write where the
  site does - on doctrine omnibridge/doctrine's unit of work over the same entity manager, as before. The commands named
  after Doctrine (doctrine:schema:charset, doctrine:array:upgrade, doctrine:database:import, base:mapping) use the entity
  manager itself, as before.
- notifier:mail:test is no longer given Doctrine's `App\Repository\UserRepository` on omnisense/omnibase 5.x (its task
  reads the accounts through the unit of work); the legacy command's `$userRepository` is nullable, 4.x still given it.
  omnisense/omnibase's UserNotification task reads the account through the users repository's loader, not
  `instanceof App\Repository\UserRepository`.

### Fixed: thread:publishable writes through the persistence chosen (omnisense/omnibase's plan "omnibase agnostic", step F7f-3)

- `Omnibase\Console\Command::setUnitOfWork()`, given `Omnibridge\Persistence\UnitOfWork` for `thread:publishable`: the
  command writes through the persistence chosen, where its threads are read (`Threads`, Eloquent's on eloquent - it
  refreshed and flushed them through Doctrine's entity manager: "is not managed", the cron published nothing). The other
  commands keep the unit of work over the constructor's entity manager (user:notifications still asks Doctrine's
  `App\Repository\UserRepository`); on doctrine the unit of work given is omnibridge/doctrine's over the same entity
  manager.

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
