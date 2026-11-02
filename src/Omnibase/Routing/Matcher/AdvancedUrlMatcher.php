<?php

namespace Omnibridge\Symfony\Omnibase\Routing\Matcher;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Contracts\Cache\ItemInterface;

class AdvancedUrlMatcher extends CompiledUrlMatcher implements RedirectableUrlMatcherInterface
{
    public static $router = null;

    public function __construct(array $compiledRoutes, RequestContext $context)
    {
        $this->context = $context;
        [$matchHost, $staticRoutes, $regexpList, $dynamicRoutes, $checkCondition] = $compiledRoutes;

        if (self::$router?->useAdvancedFeatures()) {

            //
            // NB: Static routes using multiple host, or domains might be screened.. imo
            $reservedChars = ["{", "}", "(", ")", "/", "\\", "@", ":"];
            $replacementChars = array_pad([], count($reservedChars), "_");

            // The keys carry a signature of the compiled routes. The cache pool
            // (cache.adapter) outlives cache:clear, and these entries hold the
            // routes' defaults - so without it a changed route kept being
            // matched as it used to be (API Platform routes still `_stateless`
            // after their configuration said otherwise). checkCondition is a
            // closure and cannot be serialized; the rest is what gets cached.
            // Entries of superseded routes expire instead of piling up.
            $signature = hash('xxh128', serialize([$matchHost, $staticRoutes, $regexpList, $dynamicRoutes]));

            $cacheKey = str_replace($reservedChars, $replacementChars, self::$router->getCacheName() . ".static_routes[" . $signature . "][" . self::$router->getLocale() . "][" . self::$router->getHost() . "]");
            $staticRoutes = self::$router->getCache()->get($cacheKey, function (ItemInterface $item) use (&$staticRoutes) {
                $item->expiresAfter(86400);

                foreach ($staticRoutes as &$staticRoute) {
                
                    $host = $staticRoute[0][1];
                    if (!$host) {
                        continue;
                    }

                    $ipFallback = array_key_exists("ip", parse_url2(self::$router->getHostFallback()));
                    if ($ipFallback && (str_contains($host, "\\\\\\{_machine\\\\\\}") || str_contains($host, "\\\\\\{_subdomain\\\\\\}"))) {
                        throw new Exception("IP address provided. This is incompatible with some routes using `machine` and `subdomain` features.");
                    }
                    if (!self::$router->getDomainFallback() && str_contains($host, "\\\\\\{_domain\\\\\\}")) {
                        throw new Exception("Domain fallback not provided. This is incompatible with some routes using `domain` features.");
                    }

                    $machine = self::$router->getMachine() ?? null;
                    $subdomain = self::$router->getSubdomain() ?? null;
                    $domain = self::$router->getDomain() ?? null;
                    $port = self::$router->getPort() ?? null;

                    $machine = $machine ? preg_quote($machine) : $machine;
                    $subdomain = $subdomain ? preg_quote($subdomain) : $subdomain;
                    $domain = $domain ? preg_quote($domain) : $domain;

                    $search = ["\\\\\\{_machine\\\\\\}\.", "\\\\\\{_subdomain\\\\\\}\.", "\\\\\\{_domain\\\\\\}", ":\\\\\\{_port\\\\\\}"];
                    $replace = [$machine ? $machine . "." : "", $subdomain ? $subdomain . "." : "", preg_quote($domain), $port == 80 || $port == 443 || !$port ? "" : ":" . $port];

                    $staticRoute[0][1] = str_replace($search, $replace, $host);
                }

                return $staticRoutes;
            });

            $cacheKey = str_replace($reservedChars, $replacementChars, self::$router->getCacheName() . ".dynamic_routes[" . $signature . "][" . self::$router->getLocale() . "][" . self::$router->getHost() . "]");
            [$regexpList, $dynamicRoutes] = self::$router->getCache()->get($cacheKey, function (ItemInterface $item) use (&$regexpList, &$dynamicRoutes) {
                $item->expiresAfter(86400);

                foreach ($regexpList as &$regexp) {

                    $ipFallback = self::$router->getHostFallback() ? array_key_exists("ip", parse_url2(self::$router->getHostFallback())) : false;
                    if ($ipFallback && (str_contains($regexp, "\\\\\\{_machine\\\\\\}") || str_contains($regexp, "\\\\\\{_subdomain\\\\\\}"))) {
                        throw new Exception("IP address provided. This is incompatible with some routes using `machine` and `subdomain` features.");
                    }
    
                    if (!self::$router->getDomainFallback() && str_contains($regexp, "\\\\\\{_domain\\\\\\}")) {
                        throw new Exception("Domain fallback required but not provided.");
                    }

                    $machine = self::$router->getMachine() ?? null;
                    $subdomain = self::$router->getSubdomain() ?? null;
                    $domain = self::$router->getDomain() ?? null;
                    $port = self::$router->getPort() ?? null;

                    $machine = $machine ? preg_quote($machine) : $machine;
                    $subdomain = $subdomain ? preg_quote($subdomain) : $subdomain;
                    $domain = $domain ? preg_quote($domain) : $domain;

                    $search = ["\\\\\\{_machine\\\\\\}\.", "\\\\\\{_subdomain\\\\\\}\.", "\\\\\\{_domain\\\\\\}", "\:\\\\\\{_port\\\\\\}"];
                    $replace = [$machine ? $machine . "\." : "", $subdomain ? $subdomain . "\." : "", $domain, $port == 80 || $port == 443 || !$port ? "" : "\:" . $port];
                    $regexp = str_replace($search, $replace, $regexp);
                }

                return $this->recomputeDynamicRoutes($regexpList, $dynamicRoutes);
            });
            
            $compiledRoutes = [$matchHost, $staticRoutes, $regexpList, $dynamicRoutes, $checkCondition];
        }

        return parent::__construct($compiledRoutes, $context);
    }

    public function redirect(string $path, string $route, ?string $scheme = null): array
    {
        return [
            '_controller' => RedirectController::class . '::urlRedirectAction',
            '_route' => $route,

            'path' => $path,
            'permanent' => true,
            'scheme' => $scheme,
            'httpPort' => $this->context->getHttpPort(),
            'httpsPort' => $this->context->getHttpsPort(),
        ];
    }

    public function security(string $pathinfo): bool
    {
        $request = Request::create($pathinfo, "GET", [], $_COOKIE, $_FILES, $_SERVER);
        return self::$router->getFirewallMap()?->getFirewallConfig($request)?->isSecurityEnabled() ?? false;
    }

    public function firewall(string $pathinfo): ?FirewallConfig
    {
        $request = Request::create($pathinfo, "GET", [], $_COOKIE, $_FILES, $_SERVER);
        return self::$router->getFirewallMap()?->getFirewallConfig($request);
    }

    private function recomputeDynamicRoutes(array $regexpList, array $dynamicRoutes): array
    {
        if (empty($regexpList)) {
            return [$regexpList, $dynamicRoutes];
        }

        $splitRegexp = preg_split('/\(\*:([0-9]+)\)/', $regexpList[0]);
        $lastKey = array_key_last($splitRegexp);
        $dynamicRouteKeys = array_keys($dynamicRoutes);

        $_regexpList[0] = "";
        $_dynamicRoutes = [];
        foreach ($splitRegexp as $key => $pattern) {
            $_regexpList[0] .= $pattern;
            if ($key == $lastKey) {
                continue;
            }

            $_regexpList[0] .= "(*";
            $n = strlen($_regexpList[0]);
            $_regexpList[0] .= ":" . $n . ")";

            $_dynamicRoutes[$n] = $dynamicRoutes[$dynamicRouteKeys[$key]];
        }

        return [$_regexpList, $_dynamicRoutes];
    }

    public function getCompiledPath(array $compiledRoute): ?string
    {
        $path = "";
        $parameters = array_reverse($compiledRoute ?? []);
        foreach ($parameters as $parameter) {
            $path .= $parameter[1];
            $path .= $parameter[3] ?? false ? "{" . $parameter[3] . "}" : "";
        }

        return $path;
    }

    public function getCompiledHost(array $compiledRoute): ?string
    {
        $host = null;
        foreach ($compiledRoute[4] ?? [] as $part) {
            $host = $part[1] . ($part[0] == "variable" ? "{" . $part[3] . "}" : "") . $host;
        }

        return $host;
    }

    public function getCompiledHostRegex(array $compiledRoute): ?string
    {
        $regex = null;
        foreach ($compiledRoute[4] ?? [] as $part) {
            $regex = preg_quote($part[1]) . ($part[0] == "variable" ? $part[2] : "") . $regex;
        }

        return $regex;
    }

    public function match(string $pathinfo): array
    {
        if (self::$router?->useAdvancedFeatures()) {

            $parse = parse_url2($pathinfo);
            if (!$parse || !array_key_exists("host", $parse)) {
                $parse = parse_url2(get_url()) ?? [];
            }

            $validHttpPort = json_decode($_ENV["HTTP_PORT"] ?? "[]", true);
            $validHttpsPort = json_decode($_ENV["HTTPS_PORT"] ?? "[]", true);
            $isHttpsEnv = strtobool($_ENV["HTTPS"] ?? "off");
            $scheme = $parse['scheme'] ?? ($isHttpsEnv ? 'https' : 'http');
            $port = isset($parse['port']) ? (int)$parse['port'] : null;

            // Regularize HTTP/HTTPS ports
            if (!empty($port) && in_array($port, $validHttpPort)) $httpPort = $port;
            else $httpPort = first($validHttpPort);
            if (!empty($port) && in_array($port, $validHttpsPort)) $httpsPort = $port;
            else $httpsPort = first($validHttpsPort);

            if ($scheme === 'https') {
                if (!in_array($port, $validHttpsPort)) $httpsPort = first($validHttpsPort);
            } else {
                if (!in_array($port, $validHttpPort)) $httpPort = first($validHttpPort);
            }

            // Save in context
            $this->getContext()->setHttpPort($httpPort ?? 80);
            $this->getContext()->setHttpsPort($httpsPort ?? 443);
            $this->getContext()->setHost($parse["host"] ?? "localhost");
            $this->getContext()->setQueryString($parse["query"] ?? "");

            $pathinfo = parse_url2($pathinfo)["path"] ?? "";
        }
        
        //
        // Prevent to match custom route with Symfony internal route.
        // NB: It breaks and gets infinite loop due to "_profiler*" route, if not set..

        try {
            $match = parent::match($pathinfo);
        } catch (Exception $e) {
            $match = [];
        }

        if (empty($match)) {
            $match = parent::match($pathinfo."/");
        } elseif (($match["_controller"] ?? null) == RedirectController::class . "::urlRedirectAction") {
            $match = parent::match($match["path"]);
        }

        return $match;
    }

    public static int $i = 0;
}
