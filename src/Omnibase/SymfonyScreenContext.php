<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Screen\ContextProvider;
use Base\Screen\Frame;
use Base\Screen\Site;
use Base\Screen\Viewer;
use Base\Security\SignOut;
use Base\Service\SettingBagInterface;
use Base\Service\ThemizerInterface;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Urls;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The screens' context under Symfony (plan "omnibase agnostique", C5): what
 * omnibase's kernel.request subscribers and Twig globals gave the templates,
 * asked once for the screen being answered.
 *
 * - the locale: the request's (what `|trans` speaks, `app.request.locale`);
 * - the host: the scheme, host and base path the page was asked at;
 * - the site: the host application's Twig global `site` (its name, slogan,
 *   description: the sites' App\Site\Identity) when it has one, else
 *   omnibase's settings (base.settings.title, slogan, logo, meta.description);
 * - the links: home (app_index, else "/"), login, register, logout (with the
 *   token the firewall's sign-out asks, Base\Security\SignOut, when someone is
 *   signed in), and each language's address (_switch_locale), those whose
 *   route exists;
 * - the viewer: the Access contract's user (the token storage's);
 * - the theme: the Themizer's colour scheme;
 * - the flashes: Twig's `app.flashes()` itself (read, and so taken).
 *
 * A site decorates this service (Base\Screen\ContextProvider) to add its own
 * frame fields.
 */
class SymfonyScreenContext implements ContextProvider
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly ?Urls $urls = null,
        private readonly ?Access $access = null,
        private readonly ?Locales $locales = null,
        private readonly ?SettingBagInterface $settings = null,
        private readonly ?ThemizerInterface $themizer = null,
        private readonly ?Environment $twig = null,
        /** Twig's `app` (Symfony's AppVariable, or omnibase's, which proxies it): its getFlashes(). */
        private readonly ?object $app = null,
        private readonly ?Tokens $tokens = null,
    ) {
    }

    public function frame(array $extra = []): Frame
    {
        $request = $this->requests->getCurrentRequest();

        return new Frame(
            locale: $request?->getLocale() ?? $this->locales?->getLocale() ?? 'en',
            site: $this->site(),
            links: $this->links(),
            viewer: $this->viewer(),
            theme: $this->theme(),
            extra: $extra,
            host: $request ? $request->getSchemeAndHttpHost().$request->getBasePath() : '',
        );
    }

    public function viewer(): ?Viewer
    {
        try {
            $user = $this->access?->getUser();
        } catch (\Throwable) {
            return null;
        }
        if (!\is_object($user)) {
            return null;
        }

        return new Viewer(method_exists($user, 'getId') ? (\is_int($id = $user->getId()) ? $id : null) : null, (string) (method_exists($user, '__toString') ? $user : ''));
    }

    public function flashes(string $type): array
    {
        if (null !== $this->app) {
            return array_values(array_map('strval', $this->app->getFlashes($type)));
        }
        $request = $this->requests->getCurrentRequest();
        if (!$request?->hasSession() || !method_exists($session = $request->getSession(), 'getFlashBag')) {
            return [];
        }

        return array_values(array_map('strval', $session->getFlashBag()->get($type)));
    }

    protected function site(): Site
    {
        $identity = null;
        try {
            $identity = $this->twig?->getGlobals()['site'] ?? null;
        } catch (\Throwable) {
        }
        $read = static function (mixed $object, string $name): ?string {
            if (!\is_object($object)) {
                return \is_array($object) && \is_scalar($object[$name] ?? null) ? (string) $object[$name] : null;
            }
            foreach ([$name, 'get'.ucfirst($name)] as $method) {
                if (method_exists($object, $method)) {
                    $value = $object->$method();

                    return \is_scalar($value) ? (string) $value : null;
                }
            }

            return isset($object->$name) && \is_scalar($object->$name) ? (string) $object->$name : null;
        };

        $title = $read($identity, 'name') ?? $this->setting('base.settings.title');
        $logo = $this->setting('base.settings.logo');

        return new Site(
            title: $title,
            slogan: $read($identity, 'slogan') ?? $this->setting('base.settings.slogan'),
            logo: null !== $logo && null !== $this->urls && !preg_match('#^(?:[a-z]+:)?//|^/#i', $logo) ? $this->urls->asset($logo) : $logo,
            age: date('Y'),
            description: $read($identity, 'description') ?? $this->setting('base.settings.meta.description'),
        );
    }

    /** @return array<string, string|array<string, string>|null> */
    protected function links(): array
    {
        $links = [
            'home' => $this->path('app_index') ?? '/',
            'login' => $this->path('security_login'),
            'register' => $this->path('security_register'),
            'logout' => $this->logout(),
        ];
        $locales = [];
        foreach ($this->locales?->getAvailableLocales() ?? [] as $locale) {
            $lang = strtolower(explode('-', str_replace('_', '-', $locale))[0]);
            if (null !== $address = $this->path('_switch_locale', ['_locale' => $lang])) {
                $locales[$lang] = $address;
            }
        }

        return $links + ['locales' => $locales];
    }

    /**
     * The sign-out's address. For someone signed in it carries their session's
     * token (Base\Security\SignOut); for a visitor it is the bare address, so a
     * page drawn for nobody makes no token (no session) and stays the same.
     */
    protected function logout(): ?string
    {
        if (null !== $this->tokens && null !== $this->viewer()) {
            try {
                return $this->path(SignOut::ROUTE, SignOut::parameters($this->tokens));
            } catch (\Throwable) {
            }
        }

        return $this->path(SignOut::ROUTE);
    }

    /** @return array{scheme?: string} */
    protected function theme(): array
    {
        try {
            return null === $this->themizer ? [] : ['scheme' => $this->themizer->color_scheme()];
        } catch (\Throwable) {
            return [];
        }
    }

    /** A route's address, null when the application has no such route. */
    protected function path(string $route, array $parameters = []): ?string
    {
        try {
            return $this->urls?->path($route, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    private function setting(string $path): ?string
    {
        try {
            $value = $this->settings?->getScalar($path);
        } catch (\Throwable) {
            return null;
        }

        return \is_scalar($value) && '' !== (string) $value ? (string) $value : null;
    }
}
