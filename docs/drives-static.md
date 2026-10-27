---
title: Storages and static export
order: 32
---

# Storages and static export

## Storages: omnidrive

Where [omnisense/omnidrive](https://github.com/glitchr-omnisense/omnidrive) is installed (suggested),
`Omnidrive\Drives` is the application's storages as omnidrive's drives: an
`Omnidrive\Flysystem\OperatorDrives` over the services tagged `flysystem.storage`, by their `storage`
attribute - the storages of league/flysystem-bundle's configuration (or omnibase's local ones without
the bundle). Nothing is configured a second time: `config/packages/flysystem.yaml` names them, the
code reads and writes them through `Drive`.

```yaml
# config/packages/flysystem.yaml (league/flysystem-bundle)
flysystem:
    storages:
        local.uploads:
            adapter: local
            options: { directory: '%kernel.project_dir%/public/uploads' }
```

```php
use Omnidrive\Drives;

final class Avatars
{
    public function __construct(private readonly Drives $drives) {}

    public function store(int $id, string $bytes): void
    {
        $this->drives->get('local.uploads')->write('avatars/'.$id.'.png', $bytes);
    }
}
```

| Service | Class | |
|---|---|---|
| `Omnidrive\Flysystem\OperatorDrives` | | a `tagged_locator('flysystem.storage', 'storage')` |
| `Omnidrive\Drives` (public alias) | | the application's, by the storages' names (`names()`, `get()`) |

The drive is a `FlysystemDrive` over the very operator: a file written through the drive is in the host's
storage (`DrivesTest`). What a drive does (addresses, visibility, errors) is omnidrive's manual. The
map cache's `cache.drive` names one of these drives ([Maps](omnimap.md)).

## Static export: omnibridge/static

Where [omnibridge/static](https://github.com/glitchr-omnisense/omnibridge-static) is installed,
`config/services.php` registers the console command `omnibridge:static:build`
(`Omnibridge\Symfony\Static\BuildCommand`, tag `console.command`): omnibridge/static's own command
line (`Console\Cli`), handed what this host offers the site's file.

```sh
bin/console omnibridge:static:build static.php --out=var/static [--verify] [--strict]
```

| Argument or option | Default | Effect |
|---|---|---|
| `site` | `static.php` | the file that declares the site (it returns an `Omnibridge\Static\Site`, or a closure that takes the host's offer); relative to the project directory unless absolute |
| `--out` | `var/static` | the folder to export to (emptied first if it holds an earlier export) |
| `--render-origin` | the router's default URI | the origin the pages are rendered at: the request context's scheme and host, its port unless 80 or 443 |
| `--verify` | off | render every page twice; a page that differs is an error |
| `--strict` | off | a warning fails the build too |
| `--quiet-progress` | off | errors and counts only |

What the command hands `Cli::build()` as the host's offer:

| Key | Value |
|---|---|
| `Cli::HOST_NAME` | `symfony` |
| `Cli::HOST_RENDERER` | a `RequestRenderer` over `$kernel->handle($request)`: each page a visitor's GET at the render origin, through the kernel's own request handling (firewall, listeners, controllers) |
| `Cli::HOST_ROUTES` | the router's `RouteCollection` |
| `Cli::HOST_CONTAINER` | the kernel's container |
| `Cli::HOST_PUBLIC_DIR` | `<project>/public` |

The exit code is `Cli::build()`'s (its report, its checks and its codes are omnibridge/static's
manual); an `InvalidArgumentException` it throws (a site file that is not one) is printed as an error
and the command exits with `Command::INVALID` (2).

Run on lesringostars (2026-10-24, the working copy loaded by hand, neither package installed there
yet): the same report as the plain command line, but for the dev 404 page (Symfony's exception page,
its trace).
