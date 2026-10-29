# Changelog

## [Unreleased]

### Fixed

- omnimap: `OmnimapExtension` registers omnimap/maplibre's `MapLibreFactory` when it is installed,
  beside Google's and OpenStreetMap's (`OmnimapExtension::FACTORIES`): `provider: maplibre` was
  "No maplibre factory" unless the application tagged its own service.

### Removed

- omniauth.yaml: `oauth.providers.<name>.class`, `options` and `fields` are no longer declared by the
  tree (with their defaults null, `[]`, `[]`): no factory read them. They are part of the
  configuration's public keys: an application that sets them keeps them, handed to the factories
  as any other extra key (`ignoreExtraKeys(false)`), so nothing it writes is refused.
