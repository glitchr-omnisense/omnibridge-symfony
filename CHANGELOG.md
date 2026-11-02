# Changelog

## [Unreleased]

### Added

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
