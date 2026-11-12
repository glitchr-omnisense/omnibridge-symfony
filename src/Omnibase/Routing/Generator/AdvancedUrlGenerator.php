<?php

namespace Omnibridge\Symfony\Omnibase\Routing\Generator;

use Base\Service\Localizer;
use Exception;
use Omnibridge\Symfony\Omnibase\Routing\AdvancedRouter;
use Psr\Log\LoggerInterface;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\CompiledUrlGenerator;
use Symfony\Component\Routing\RequestContext;

class AdvancedUrlGenerator extends CompiledUrlGenerator
{
    public static $router = null;

    /**
     * omnibase's router, made when a generator first needs it (AdvancedRouter::current()): the router's options name
     * this class from the container's compilation (AdvancedRouterPass), the kernel's boot no longer makes the router
     * for that. None where no kernel handed its container and none was set: the generator is then Symfony's, as it
     * was before the router was made and named this class.
     */
    private static function router(): mixed
    {
        // Not loaded: no container was handed (setContainer() loads it), and loading it needs omnisense/omnibase.
        return self::$router ??= class_exists(AdvancedRouter::class, false) ? AdvancedRouter::current() : null;
    }

    protected array $cachedRoutes = [];
    protected array $compiledRoutes;
    /** Made with omnibase's router: else Symfony's generator, whatever router comes later. */
    private bool $advanced = true;

    public function getCompiledRoutes(): array
    {
        return $this->compiledRoutes;
    }

    public function __construct(array $compiledRoutes, RequestContext $context, ?LoggerInterface $logger = null, ?string $defaultLocale = null)
    {
        $this->context = $context;
        $this->advanced = null !== self::router();
        if (!$this->advanced) {
            parent::__construct($compiledRoutes, $context, $logger, $defaultLocale);
            $this->compiledRoutes = $compiledRoutes;

            return;
        }

        //
        // NB: Static routes using multiple host, or domains might be screened.. imo
        $reservedChars = ["{", "}", "(", ")", "/", "\\", "@", ":"];
        $replacementChars = array_pad([], count($reservedChars), "_");
        $cacheKey = str_replace($reservedChars, $replacementChars, self::router()->getCacheName() . ".compiled_routes[" . self::router()->getLocale() . "][" . self::router()->getHost() . "]");
        $compiledRoutes = self::router()->getCache()->get($cacheKey, function () use ($compiledRoutes) {
            foreach ($compiledRoutes as &$compiledRoute) {
                $machine = self::router()->getMachine() ?? null;
                $subdomain = self::router()->getSubdomain() ?? null;
                $domain = self::router()->getDomain() ?? null;
                $port = self::router()->getPort() ?? null;

                $search = ["\\{_machine\\}.", "\\{_subdomain\\}.", "\\{_domain\\}", ":\\{_port\\}"];
                $replace = [$machine ? $machine . "." : "", $subdomain ? $subdomain . "." : "", $domain, $port == 80 || $port == 443 || !$port ? "" : ":" . $port];

                foreach ($compiledRoute[4] as &$variable) {
                    $variable[1] = str_replace($search, $replace, $variable[1]);
                }
            }

            return $compiledRoutes;
        });

        parent::__construct($compiledRoutes, $context, $logger, $defaultLocale);

        $this->compiledRoutes = $compiledRoutes;
    }

    protected function resolveCandidates(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH, ?string $locale = null): array
    {
        $routeCandidates = [];
        $locale ??= $routeParameters["_locale"] ?? self::router()->getLocalizer()->getLocaleLang();
    
        if (!str_ends_with($routeName, "." . $locale)) {
            $localizedAppRouteName = $routeName . "." . $locale;
            $routeCandidates[] = $localizedAppRouteName;
        }
    
        $routeCandidates[] = $routeName;
        $routeGroups = self::router()->getRouteGroups($routeName);

        // No group for a route the router does not know: its own name (str_ends_with() and substr() were given null, deprecated since PHP 8.1, before RouteNotFoundException).
        $routeDefaultName = first($routeGroups) ?? $routeName;
        if (!str_ends_with($routeDefaultName, "." . $locale)) {
            $routeCandidates[] = $routeDefaultName . "." . $locale;
        }
    
        $routeCandidates[] = $routeDefaultName;

        if (str_starts_with($routeName, 'app_')) {
            $routeName = 'base_' . substr($routeName, 4);
    
            if (!str_ends_with($routeName, "." . $locale)) {
                $routeCandidates[] = $routeName . "." . $locale;
            }
    
            $routeCandidates[] = $routeName;

            $routeDefaultName = 'base_' . substr($routeDefaultName, 4);
            if (!str_ends_with($routeDefaultName, "." . $locale)) {
                $routeCandidates[] = $routeDefaultName . "." . $locale;
            }

            $routeCandidates[] = $routeDefaultName;
        }

        // The candidates in their order of preference - the visitor's locale
        // first - then the rest of the group. (Sorted by name, "app_x" came
        // before "app_x.de": the first URL of a route in a request was the
        // default locale's, whatever the visitor's language.)
        $routes = [];
        foreach (array_unique(array_merge($routeCandidates, $routeGroups)) as $candidate) {
            if ($candidate && !array_key_exists($candidate, $routes) && ($route = self::router()->getRoute($candidate))) {
                $routes[$candidate] = $route;
            }
        }

        return $routes;
    }

    public function resolveParameters(?array $routeParameters = null): ?array
    {
        if ($routeParameters === null) {

            $parse = parse_url2(get_url(), -1, self::router()->getBaseDir()); // Make sure also it gets the basic context

        } else {

            // Use either parameters or $_SERVER variables to determine the host to provide
            $scheme = array_pop_key("_scheme", $routeParameters) ?? $this->getContext()->getScheme();
            $baseDir = array_pop_key("_base_dir", $routeParameters) ?? $this->getContext()->getBaseUrl();
            $host = array_pop_key("_host", $routeParameters) ?? $this->getContext()->getHost();
            $port = array_pop_key("_port", $routeParameters) ?? explode(":", $host)[1] ?? $this->getContext()->getHttpPort();
            $host = explode(":", $host)[0] . ($port == 80 || $port == 443 || !$port) ? "" : (":" . $port);

            $parse = parse_url2(get_url($scheme, $host, $baseDir), -1, $baseDir);
            $parse["base_dir"] = $baseDir;
        }

        if (is_array($parse)) {
            if ($parse && array_key_exists("host", $parse)) {
                $this->getContext()->setHost($parse["host"]);
            }
            if ($parse && array_key_exists("base_dir", $parse)) {
                $this->getContext()->setBaseUrl($parse["base_dir"]);
            }

            $validHttpPort = json_decode($_ENV["HTTP_PORT"] ?? "[]", true);
            $validHttpsPort = json_decode($_ENV["HTTPS_PORT"] ?? "[]", true);
            $isHttpsEnv = strtobool($_ENV["HTTPS"] ?? "off");
            $scheme = $parse['scheme'] ?? ($isHttpsEnv ? 'https' : 'http');
            $port = isset($parse['port']) ? (int)$parse['port'] : null;

            // Regularize HTTP/HTTPS ports
            if (!empty($port) && in_array($port, $validHttpPort)) $httpPort = $port;
            else $httpPort = first($validHttpPort) ?? $port;
            if (!empty($port) && in_array($port, $validHttpsPort)) $httpsPort = $port;
            else $httpsPort = first($validHttpsPort) ?? $port;

            if ($scheme === 'https') {
                if (count($validHttpsPort) && !in_array($port, $validHttpsPort)) $httpsPort = first($validHttpsPort);
            } else {
                if (count($validHttpPort) && !in_array($port, $validHttpPort)) $httpPort = first($validHttpPort);
            }

            // Save in context
            $this->getContext()->setHttpPort($httpPort ?? 80);
            $this->getContext()->setHttpsPort($httpsPort ?? 443);
            $this->getContext()->setScheme($parse["scheme"] ?? "https");
            $this->getContext()->setQueryString($parse["query"] ?? "");
        }

        return array_filter($routeParameters, fn($p) => $p !== null);
    }

    public function generate(string $routeName, array $routeParameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        if (!$this->advanced) {
            return parent::generate($routeName, $routeParameters, $referenceType);
        }

        $referenceType = array_key_exists("_host", $routeParameters) && $routeParameters["_host"] !== null ? self::ABSOLUTE_URL : $referenceType;

        //
        // Prevent to generate custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..
        if (str_starts_with($routeName, "_") || !self::router()->useAdvancedFeatures()) {
            $routeParameters = $this->resolveParameters($routeParameters);
            $routeUrl = parent::generate($routeName, $routeParameters, $referenceType);
            $routeUrl = str_replace(
                ["http://", "https://"], // Ignore forced scheme from Symfony internal route generation
                [self::router()->getScheme() . "://", self::router()->getScheme() . "://"], $routeUrl
            );

            return $routeUrl;
        }

        //
        // Extract locale from route name if found ("app_x.en": English, whatever the visitor's)
        $locale = null;
        foreach (self::router()->getLocalizer()->getAvailableLocaleLangs() as $lang) {
            if (str_ends_with($routeName, "." . $lang)) {
                $routeName = str_rstrip($routeName, "." . $lang);
                $locale = $lang;
            }
        }

        // Update context, transforms requested route by adding parameters
        if (($route = self::router()->getRoute($routeName))) {
            
            $isHttpsEnv = strtobool($_ENV["HTTPS"] ?? "off");
            
            $host = $route->getHost() ? $route->getHost() : self::router()->getHost();
            // The router's host may come with its scheme ("https://localhost"): cut at the first
            // colon, it left "https" as the host - url() answered https://https/... wherever no
            // request gives the host again (a test, a command, a worker).
            $host = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $host);
            $host = explode(":", $host)[0]; // Force removing port from host..
            $this->getContext()->setHost($host);

            $scheme = self::router()->getScheme() ?? $this->getContext()->getScheme();
            $port = self::router()->getPort() ?? $this->getContext()->getHttpPort();
            // A route with a host of its own is written whole. A visitor on http
            // where the site sets HTTPS no longer forces it: getScheme() is the
            // request's now, and the address would carry the request's own
            // scheme and host, nothing a path does not say.
            if ($route->getHost()) {
                $referenceType = self::ABSOLUTE_URL;
            }

            $validHttpPort = json_decode($_ENV["HTTP_PORT"] ?? "[]", true);
            $validHttpsPort = json_decode($_ENV["HTTPS_PORT"] ?? "[]", true);
            $scheme = $scheme ?? ($isHttpsEnv ? 'https' : 'http');
            $port = isset($port) ? (int)$port : null;
            
            // Regularize HTTP/HTTPS ports
            if (!empty($port) && in_array($port, $validHttpPort)) $httpPort = $port;
            else $httpPort = first($validHttpPort);
            if (!empty($port) && in_array($port, $validHttpsPort)) $httpsPort = $port;
            else $httpsPort = first($validHttpsPort);

            if ($scheme === 'https') {
                if (count($validHttpsPort) && !in_array($port, $validHttpsPort)) $httpsPort = first($validHttpsPort);
            } else {
                if (count($validHttpPort) && !in_array($port, $validHttpPort)) $httpPort = first($validHttpPort);
            }

            // Save in context
            $this->getContext()->setHttpPort($httpPort ?? 80);
            $this->getContext()->setHttpsPort($httpsPort ?? 443);
            $path = $route->getPath();

            if (str_contains($host . $path, "{") && str_contains($host . $path, "}")) {
                if (preg_match_all("/{(\w*)}/", $host . $path, $matches)) {
                    $parse = array_transforms(fn($k, $v): array => ["_" . $k, $v], parse_url2(get_url()));
                    $parameterNames = array_flip($matches[1]);

                    $routeParameters = array_merge(
                        array_intersect_key($parse, $parameterNames),
                        $route->getDefaults(),
                        $routeParameters,
                    );

                    $search = array_map(fn($k) => "{" . $k . "}", array_keys($parse));
                    $replace = array_values($parse);
                    foreach ($routeParameters as $key => $routeParameter) {
                        if (!$routeParameter) {
                            continue;
                        }

                        if (is_string($routeParameter)) {
                            $routeParameters[$key] = str_replace($search, $replace, $routeParameter);
                        } else {
                            $routeParameters[$key] = $routeParameter;
                        }
                    }
                }
            }
        }

        $routeParameters = $this->resolveParameters($routeParameters);

        // Check whether the route is already cached
        $hash = self::router()->getRouteHash($routeName, $routeParameters, $referenceType);
        if (array_key_exists($hash, $this->cachedRoutes) && $this->cachedRoutes[$hash]["_name"] !== null) {
            $cachedRoute = $this->cachedRoutes[$hash];

            $locale ??= array_key_exists("_locale", $routeParameters) ? self::router()->getLocalizer()->getLocaleLang($routeParameters["_locale"]) : self::router()->getLocalizer()->getLocaleLang();
            try {
                return self::withoutEmptySegments(parent::generate($cachedRoute["_name"] . ($locale ? "." . $locale : ""), $routeParameters, $referenceType), $referenceType);
            } catch (Exception $_) {
                return self::withoutEmptySegments(parent::generate($cachedRoute["_name"], $routeParameters, $referenceType), $referenceType);
            }
        }

        $routes = $this->resolveCandidates($routeName, $routeParameters, $referenceType, $locale);
        foreach($routes as $routeName => $route) {

            try {
                $routeUrl = parent::generate($routeName, $routeParameters, $referenceType);
            } catch(\Exception $_) {
                continue;
            }

            // Remembered for the rest of the request. It was also written to the router's cache pool after each
            // route found - 16 to 20 writes of the whole list on a page of lesringostars, 1.3 to 1.7 ms -, a pool
            // nothing ever read back: every request started from an empty list.
            $routeRequirements = [];
            if (array_key_exists($routeName, $routes)) {
                $routeRequirements = $routes[$routeName]->getRequirements();
            }

            // Cached without its locale: the cached path appends the visitor's.
            $lang = $locale ?? (array_key_exists("_locale", $routeParameters) ? self::router()->getLocalizer()->getLocaleLang($routeParameters["_locale"]) : self::router()->getLocalizer()->getLocaleLang());
            $this->cachedRoutes[$hash] = [
                "_name" => $lang && str_ends_with($routeName, "." . $lang) ? substr($routeName, 0, -strlen("." . $lang)) : $routeName,
                "_requirements" => $routeRequirements
            ];

            // As the router wrote it, as a route found in the cache above is given: sanitize_url() here made
            // the first address of a route in the cache's life differ from every later one - a slash before
            // the query (/chroniques/?page=2, then /chroniques?page=2), the root's slash dropped
            // (http://localhost, then http://localhost/) -, so a page's links depended on the order they
            // were made in. Only an empty segment of the path is dropped, the first time as every time after:
            // a variable given empty (path("app_wishlist", {store: null})) made /boutique//wishlist where the
            // router does not check requirements (strict_requirements: null), which sanitize_url() cleaned
            // in the first address only.
            return self::withoutEmptySegments($routeUrl, $referenceType);
        }

        throw new RouteNotFoundException("No valid route found for `$routeName`. Candidate routes are ". implode(", ", array_keys($routes)). "(please check mandatory variables?)");
    }

    /**
     * The address without an empty segment in its path ("/boutique//wishlist" is "/boutique/wishlist"); its
     * scheme, host, query and fragment as they were, a path's trailing slash kept. A network path
     * (//host/path, UrlGeneratorInterface::NETWORK_PATH) keeps its leading host.
     */
    public static function withoutEmptySegments(string $url, int $referenceType = self::ABSOLUTE_PATH): string
    {
        if (!str_contains($url, "//")) {
            return $url;
        }

        $cut = strcspn($url, "?#");
        $authority = $referenceType === self::NETWORK_PATH ? '~^//[^/?#]*~' : '~^[a-z][a-z0-9+.-]*://[^/?#]*~i';
        $start = preg_match($authority, $url, $match) ? strlen($match[0]) : 0;
        $path = substr($url, $start, $cut - $start);

        return substr($url, 0, $start) . preg_replace('#/{2,}#', '/', $path) . substr($url, $cut);
    }

    /** The compiled routes' names by their name before the first dot, in their order, each preceded by its name without the default locale. */
    private ?array $groupIndex = null;

    /**
     * The routes of a group: the route named before its first dot and its variants ("app_x", "app_x.fr"...), in
     * the compiled order. Read from an index made once, at the first call: it scanned the 575 compiled routes
     * of lesringostars at every call, 2.4 to 3 ms of a page.
     */
    public function groups(?string $routeName): array
    {
        $routeName = explode(".", $routeName ?? "")[0];

        if ($this->groupIndex === null) {
            $this->groupIndex = [];
            $suffix = "." . Localizer::getDefaultLocaleLang();
            foreach (array_keys($this->getCompiledRoutes()) as $_routeName) {
                $group = explode(".", $_routeName)[0];
                $_routeNameWithoutLocale = str_rstrip($_routeName, $suffix);
                if ($_routeName != $_routeNameWithoutLocale) {
                    $this->groupIndex[$group][] = $_routeNameWithoutLocale;
                }

                $this->groupIndex[$group][] = $_routeName;
            }
        }

        return array_unique($this->groupIndex[$routeName] ?? []);
    }
}
