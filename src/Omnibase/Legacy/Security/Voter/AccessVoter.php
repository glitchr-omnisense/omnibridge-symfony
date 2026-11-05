<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\Security\Voter;

use App\Entity\User;
use Base\Service\LocalizerInterface;
use Base\Service\MaintenanceProviderInterface;
use Base\Service\launcherInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\Referrer;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;

class AccessVoter extends Voter
{
    public const      LAUNCH_ACCESS = "LAUNCH_ACCESS";
    public const MAINTENANCE_ACCESS = "MAINTENANCE_ACCESS";
    public const   EXCEPTION_ACCESS = "EXCEPTION_ACCESS";

    public const ANONYMOUS_ACCESS = "ANONYMOUS_ACCESS";
    public const      USER_ACCESS = "USER_ACCESS";
    public const     ADMIN_ACCESS = "ADMIN_ACCESS";

    /**
     * @var RouterInterface
     * */
    protected RouterInterface $router;
    /**
     * @var ParameterBagInterface
     * */
    protected ParameterBagInterface $parameterBag;
    /**
     * @var LocalizerInterface
     * */
    protected LocalizerInterface $localizer;
    /**
     * @var MaintenanceProviderInterface
     * */
    protected MaintenanceProviderInterface $maintenanceProvider;
    /**
     * @var LauncherInterface
     * */
    protected LauncherInterface $launcher;

    protected ?array $urlExceptions;

    // Request-scoped cache for parameter bag reads
    private ?bool $adminAccessCache = null;
    private ?bool $userAccessCache = null;
    private ?bool $publicAccessCache = null;
    private ?FirewallConfig $currentFirewallCache = null;

    public function __construct(RouterInterface $router, ParameterBagInterface $parameterBag, LocalizerInterface $localizer, MaintenanceProviderInterface $maintenanceProvider, LauncherInterface $launcher)
    {
        $this->router = $router;
        $this->parameterBag = $parameterBag;
        $this->localizer = $localizer;
        $this->maintenanceProvider = $maintenanceProvider;
        $this->launcher = $launcher;

        // Fetch exceptions once to avoid duplicate parameter bag calls
        $exceptions = $this->parameterBag->get("base.access_restriction.exceptions");
        $this->urlExceptions   = array_search_by($exceptions, "locale", $this->localizer->getLocale());
        $this->urlExceptions ??= array_search_by($exceptions, "locale", $this->localizer->getLocaleLang());
        $this->urlExceptions ??= array_search_by($exceptions, "locale", $this->localizer->getDefaultLocale());
        $this->urlExceptions ??= array_search_by($exceptions, "locale", $this->localizer->getDefaultLocaleLang());
        $this->urlExceptions ??= array_search_by($exceptions, "locale", null) ?? [];
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXCEPTION_ACCESS, self::MAINTENANCE_ACCESS, self::LAUNCH_ACCESS, self::ANONYMOUS_ACCESS, self::USER_ACCESS, self::ADMIN_ACCESS]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // Initialize request-scoped cache for parameter bag reads
        if ($this->adminAccessCache === null) {
            $this->adminAccessCache = filter_var($this->parameterBag->get("base.access_restriction.admin_access"), FILTER_VALIDATE_BOOLEAN);
        }
        if ($this->userAccessCache === null) {
            $this->userAccessCache = filter_var($this->parameterBag->get("base.access_restriction.user_access"), FILTER_VALIDATE_BOOLEAN);
        }
        if ($this->publicAccessCache === null) {
            $this->publicAccessCache = filter_var($this->parameterBag->get("base.access_restriction.public_access"), FILTER_VALIDATE_BOOLEAN);
        }

        $user = $subject instanceof User ? $subject : null;
        $url = is_string($subject) || $subject instanceof Referrer ? $subject : get_url();

        switch ($attribute) {

            case self::ADMIN_ACCESS:
                $access = $this->adminAccessCache;
                $access |= $user && $user->isGranted("ROLE_SUPERADMIN");
                return $access;

            case self::USER_ACCESS:
                $access = $this->userAccessCache;
                $access |= $this->adminAccessCache && $user && $user->isGranted("ROLE_ADMIN");
                return $access;

            case self::ANONYMOUS_ACCESS:
                $access = $this->publicAccessCache;
                $access |= ($this->adminAccessCache || $this->userAccessCache) && $user && $user->isGranted("ROLE_USER");

                return $access;

            case self::MAINTENANCE_ACCESS:
                return !$this->maintenanceProvider->isUnderMaintenance() || $this->voteOnAttribute(self::EXCEPTION_ACCESS, $subject, $token);

            case self::LAUNCH_ACCESS:
                return $this->launcher->isLaunched() || $this->voteOnAttribute(self::EXCEPTION_ACCESS, $subject, $token);

            case self::EXCEPTION_ACCESS:

                // Check if firewall is subjected to restriction
                $firewallNames = $this->parameterBag->get("base.access_restriction.firewalls");
                $isRestrictedFirewall = false;

                // Cache firewall resolution (expensive URL pattern matching)
                if ($this->currentFirewallCache === null) {
                    $this->currentFirewallCache = $this->router->getRouteFirewall($url);
                }
                $firewall = $this->currentFirewallCache;
                if ($firewall == null) {
                    return true;
                }

                foreach ($firewallNames as $firewallName) {
                    $isRestrictedFirewall |= $firewall->getName() == $firewallName;
                }

                if (!$isRestrictedFirewall) {
                    return true;
                }

                $url = parse_url($url);
                foreach ($this->urlExceptions as $urlException) {
                    
                    $exception = true;

                    $environment = $urlException["env"] ?? null;
                    if ($environment !== null) {
                        $exception &= $environment == $this->router->getEnvironment();
                    }

                    $locale = $urlException["locale"] ?? null;
                    if ($locale !== null) {
                        $exception &= $locale == $this->localizer->getLocale();
                    }

                    $country = $urlException["country"] ?? null;
                    if ($country !== null) {
                        $exception &= $country == $this->localizer->getLocaleCountry();
                    }

                    $lang = $urlException["lang"] ?? null;
                    if ($lang !== null) {
                        $exception &= $lang == $this->localizer->getLocaleLang();
                    }

                    $scheme = $urlException["scheme"] ?? null;
                    if ($scheme !== null) {
                        $exception &= array_key_exists("scheme", $url) && preg_match("/" . $scheme . "/", $url["scheme"]);
                    }

                    $host = $urlException["host"] ?? null;
                    if ($host !== null) {
                        $exception &= array_key_exists("host", $url) && preg_match("/" . $host . "/", $url["host"]);
                    }

                    $domain = $urlException["domain"] ?? null;
                    if ($domain !== null) {
                        $exception &= array_key_exists("domain", $url) && preg_match("/" . $domain . "/", $url["domain"]);
                    }

                    $subdomain = $urlException["subdomain"] ?? null;
                    if ($subdomain !== null) {
                        $exception &= array_key_exists("subdomain", $url) && preg_match("/" . $subdomain . "/", $url["subdomain"]);
                    }

                    $path = $urlException["path"] ?? null;
                    if ($path !== null) {
                        $exception &= array_key_exists("path", $url) && preg_match("/" . $path . "/", $url["path"]);
                    }

                    if ($exception) {
                        return true;
                    }
                }

            // no break
            default:
                return false;
        }
    }
}
