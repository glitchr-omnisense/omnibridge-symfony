---
title: Maps (omnimap)
order: 31
---

# Maps (omnimap)

Where [omnisense/omnimap](https://github.com/glitchr-omnisense/omnimap) is installed (suggested),
`OmnibridgeBundle` registers omnimap's extension (`Map\DependencyInjection\OmnimapExtension`, alias
`omnimap`), and `config/packages/omnimap.yaml` wires it: its providers (omnimap/google,
omnimap/openstreetmap, registered when installed) over the application's `http_client`, a PSR-16
view of a cache pool and a PSR-18 client for geocoder-php; the map cache over a storage of the
application's (an omnidrive drive, or any `Omnimap\Snapshot\Storage`); omnibridge/twig's
`MapExtension` (`omnimap(view)`, `omnimap_export()`, `omnimap_suppress()`); the cache's routes and
their controller. With no `omnimap.yaml`, nothing is wired.

What a map, a view, a snapshot and a provider are is omnimap's own manual
([omnimap's Symfony page](https://github.com/glitchr-omnisense/omnimap/blob/1.x/docs/symfony.md),
[its cache](https://github.com/glitchr-omnisense/omnimap/blob/1.x/docs/cache.md)); this page is the
reference of the Symfony side.

## Setting it up

```yaml
# config/routes/omnimap.yaml
omnimap: { resource: ., type: omnimap }

# config/packages/omnimap.yaml
omnimap:
    provider: openstreetmap            # the page's map: a key of providers
    secret: '%env(MAP_SECRET)%'        # signs the pictures' names
    providers:
        openstreetmap: { user_agent: 'MySite/1.0 (+https://example.org)' }
        google: { api_key: '%env(GOOGLE_MAPS_CLIENT_KEY)%', secret: '%env(GOOGLE_MAPS_SECRET)%' }
    cache:
        drive: local.uploads           # an omnidrive drive (Omnidrive\Drives); or storage: an Omnimap\Snapshot\Storage service
        public_directory: '%kernel.project_dir%/public'   # tiles served are written there too
    routes:
        tile: { path: '/omnimap/{signature}/{id}.webp', name: omnimap_tile }
```

`cache.drive` needs omnisense/omnidrive (its `Omnidrive\Drives` is bound by this package,
[Storages](drives-static.md#storages-omnidrive)).

## The configuration tree

Dumped with `bin/console config:dump-reference omnimap` on the same kernel as omniauth's (PHP 8.4,
Symfony 8.1.9, 2026-10-27):

```yaml
# Default configuration for extension with alias: "omnimap"
omnimap:

    # The provider of the page's map: a key of "providers".
    provider:             ~ # Required

    # The provider that geocodes (Omnimap\GeocoderInterface): a key of "providers"; the page's provider by default.
    geocoder:             null

    # Signs the pictures' names (URL-safe base64; Google's URL signing secret fits).
    secret:               ''
    providers:

        # Prototype
        name:                 ~

    # A visitor never gets the live map: the picture kept, or an empty frame.
    cache_only:           true

    # Who may capture and delete pictures, and sees the live map while there is none.
    control:              ROLE_ADMIN
    cache:

        # The omnidrive drive the cache is kept on (Omnidrive\Drives: a storage's name, "local.uploads"); or storage.
        drive:                null

        # A service implementing Omnimap\Snapshot\Storage, when the cache is on no omnidrive drive.
        storage:              null
        directory:            omnimap
        pattern:              '{signature}/image-{id}'
        format:               png # One of "png"; "jpeg"; "jpg"; "webp"
        tilesize:             512

        # Whether the server makes a missing picture itself: auto (when the provider makes images), true, false.
        make:                 auto
        size:                 1512x938
        scale:                1

        # The PSR-6 pool keeping fetched tiles and geocoders' answers.
        pool:                 cache.app

        # The HTTP lifetime of a tile served by the route.
        max_age:              86400

        # Where a tile served is also written (the web server then serves it without PHP): the public folder.
        public_directory:     null

        # The largest side of a tile served, in pixels (none: as kept).
        public_size:          null
    html:
        class:                omnimap-snapshot
        suffix:               _snapshot
        missing:              ''
    routes:
        tile:
            path:                 '/omnimap/{signature}/{id}.webp'
            name:                 omnimap_tile
        metadata:
            path:                 '/omnimap/{signature}'
            name:                 omnimap_metadata
        export:
            path:                 '/omnimap/{signature}/export'
            name:                 omnimap_export
        suppress:
            path:                 '/omnimap/{signature}/suppress'
            name:                 omnimap_suppress

```

| Key | Type | Default | Effect |
|---|---|---|---|
| `provider` | string, required, not empty | - | the provider of the page's map: a key of `providers` (`Omnimap\MapRendererInterface`) |
| `geocoder` | string | null (the page's provider) | the provider that geocodes (`Omnimap\GeocoderInterface`) |
| `secret` | string | `''` | signs the pictures' names (URL-safe base64; Google's URL signing secret fits) |
| `providers.<name>` | any (a map) | - | the provider's options, its factory's ([omnimap/google](https://github.com/glitchr-omnisense/omnimap-google), [omnimap/openstreetmap](https://github.com/glitchr-omnisense/omnimap-openstreetmap)); `factory` names another factory than the key |
| `cache_only` | bool | true | a visitor never gets the live map: the picture kept, or an empty frame |
| `control` | string | `ROLE_ADMIN` | the attribute (asked of the `Access` contract) that may capture and delete pictures, and sees the live map while there is none |
| `cache.drive` | string | null | the omnidrive drive the cache is kept on (`Omnidrive\Drives`: a storage's name) |
| `cache.storage` | service id | null | a service implementing `Omnimap\Snapshot\Storage`, in place of a drive; one of `drive` or `storage` is required ("omnimap.cache needs a drive ... or a storage ...") |
| `cache.directory` | string | `omnimap` | the folder of the pictures in the storage |
| `cache.pattern` | string | `{signature}/image-{id}` | a picture's file name |
| `cache.format` | `png`, `jpeg`, `jpg`, `webp` | `png` | how pictures are kept |
| `cache.tilesize` | int | 512 | the side of the tiles a picture is cut into; 0 or less: not cut |
| `cache.make` | `auto`, true, false | `auto` | whether the server makes a missing picture itself: auto when the page's provider can make static maps, true always (another static provider of the registry when the page's cannot), false never |
| `cache.size` | `WxH` | `1512x938` | the size of a picture the server makes |
| `cache.scale` | int, 1 or 2 | 1 | its scale |
| `cache.pool` | service id | `cache.app` | the PSR-6 pool keeping fetched tiles and geocoders' answers (`omnimap.cache`, a `Psr16Cache` over it) |
| `cache.max_age` | int | 86400 | the HTTP lifetime of a tile served by the route |
| `cache.public_directory` | string | null | where a tile served is also written, at the route's own path, so the web server serves it without PHP the next time |
| `cache.public_size` | int | null | the largest side of a tile converted to WebP |
| `html.class`, `html.suffix`, `html.missing` | string | `omnimap-snapshot`, `_snapshot`, `''` | omnimap's `MapHtml` (the picture's markup); `src` is the tile route's path |
| `routes.<tile, metadata, export, suppress>.path`, `.name` | string | below | the cache's routes |

## The services

| Id | Class | What it is |
|---|---|---|
| `Omnimap\Registry` (public) | | the providers: the factories tagged `omnimap.provider_factory`, the `providers` options by name |
| `Omnimap\Google\GoogleFactory`, `Omnimap\OpenStreetMap\OpenStreetMapFactory` | | registered when their package is installed, over `http_client`, `omnimap.psr18` (or null), `omnimap.cache`; tag `omnimap.provider_factory` |
| `Omnimap\MapRendererInterface` | | `Registry::renderer(provider)` |
| `Omnimap\GeocoderInterface` | | `Registry::geocoder(geocoder ?? provider)` |
| `omnimap.cache` | `Symfony\Component\Cache\Psr16Cache` | over `cache.pool` |
| `omnimap.psr18` | `Symfony\Component\HttpClient\Psr18Client` | over `http_client`, when nyholm/psr7 or php-http/discovery is installed (geocoder-php's needs) |
| `Omnimap\Snapshot\SnapshotStore` | | over `cache.storage`, or a `DriveStorage` over `Drives::get(cache.drive)` |
| `Omnimap\Snapshot\Snapshots` (public) | | the renderer, a `Signer` of `secret`, the store, the static map (per `cache.make`), the size, the scale, the tile size |
| `Omnimap\MapHtml` | | the `html` options |
| `Omnimap\MapPrinter` (public) | | the page's map by omnimap's rule; a picture the server could not make is logged as a warning (`omnimap: the map's picture could not be made: ...`) on `logger` |
| `Omnibridge\Twig\MapExtension` | | when omnibridge/twig's class exists and TwigBundle is registered; tag `twig.extension` |
| `Omnibridge\Symfony\Map\SnapshotController` (public) | | tag `controller.service_arguments` |
| `Omnibridge\Symfony\Map\MapRouteLoader` | | tag `routing.loader`, type `omnimap` |

> [!WARNING]
> Known defect: only `GoogleFactory` and `OpenStreetMapFactory` are registered, where every installed
> omnimap provider is expected to be wired: `provider: maplibre` (omnimap/maplibre) is "No maplibre
> factory" unless the application tags its own service `omnimap.provider_factory`.

```yaml
# config/services.yaml - another provider's factory
services:
    Omnimap\MapLibre\MapLibreFactory:
        arguments: ['@http_client']
        tags: [omnimap.provider_factory]
```

## The Twig functions

`Omnibridge\Twig\MapExtension` ([omnibridge/twig](https://github.com/glitchr-omnisense/omnibridge-twig)),
given the `MapPrinter`, the `Urls`, `Access` and `Tokens` contracts, `control` and the routes' names:

| Function | Output |
|---|---|
| `omnimap(view)` | the picture kept, or the live map, by `MapPrinter`'s rule (HTML); `''` for a null view |
| `omnimap_export(view, attributes = {})` | the button that sends the live map's picture to the cache, for whoever `Access` grants `control`; posts to the export route with a token of intent `omnimap-export`; `attributes.text` is its content (default `O`) |
| `omnimap_suppress(view, attributes = {})` | the button that deletes the picture kept, for the same; intent `omnimap-suppress`; default text `X` |
| `omnimap_signature(view)` | the view's signature (the picture's name) |

```twig
{{ omnimap(view) }}
{{ omnimap_export(view, {class: 'item', text: 'Capture'}) }}
{{ omnimap_suppress(view, {text: 'Delete'}) }}
```

## The routes and the controller

`omnimap: { resource: ., type: omnimap }` loads (`MapRouteLoader`, the tile first because its path
may lie under the metadata's), `signature` matching `[A-Za-z0-9_-]{1,128}`:

| Name | Path | Methods | Action |
|---|---|---|---|
| `omnimap_tile` | `/omnimap/{signature}/{id}.webp` (`id`: digits, default 0) | GET, HEAD | a tile of a picture kept |
| `omnimap_export` | `/omnimap/{signature}/export` | POST | a picture captured by the browser |
| `omnimap_suppress` | `/omnimap/{signature}/suppress` | POST | the picture deleted |
| `omnimap_metadata` | `/omnimap/{signature}` | GET, HEAD | the picture's metadata |

`SnapshotController`:

- **tile**: the tile's bytes from the store (404 "No such map tile." when none or the signature is
  invalid). When the route's path ends in `.webp` and the store keeps another format, converted to
  WebP at quality 75 and fitted within `public_size` - with GD when it was built with WebP, else
  Imagick, else served as kept. Written also under `public_directory` at the route's path. Public,
  `max-age` of `max_age`, `must-revalidate`, an ETag (MD5 of the bytes), 304 when the request's
  matches.
- **metadata**: the picture's metadata as JSON, `{"status": "BAD"}` when none.
- **export**: for whoever `Access` grants `control` (403 "Only whoever may change the map." otherwise,
  and when no `Access` is bound), with a valid `_token` of intent `omnimap-export` when `Tokens` is
  bound (403 "Invalid token."); `image` (a `data:` URL; 400 "No picture was sent." when empty),
  `tilesize` (else `cache.tilesize`); the picture saved, the public tiles of that signature deleted,
  its metadata answered. An invalid picture is a 400 with omnimap's message.
- **suppress**: the same guard with intent `omnimap-suppress`; the picture deleted with its public
  tiles; `{"status": "OK"}`.

The public tiles are deleted only inside `public_directory` and in a folder whose path holds the
signature.
