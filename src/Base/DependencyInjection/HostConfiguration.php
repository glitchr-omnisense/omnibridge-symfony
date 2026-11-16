<?php

namespace Base\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * What a site's configuration no longer writes: the classes omnibase gives Symfony's security, Doctrine, the framework
 * and WebauthnBundle, prepended from the options of `base:` - the source a Laravel host reads in config/base.php -
 * before the site's own files are merged, which keep what is the site's (its firewalls' patterns, its access
 * control, its connections) and win wherever they say otherwise.
 *
 *     base:
 *         security:
 *             firewall: main      # the visitors' firewall (null: none wired)
 *             rescue: rescue      # the rescue door's
 *             sign_in: ~          # form | omniauth (null: omniauth where it is configured)
 *             access_token: true
 *             provider: users
 *
 * A site's security.yaml then names no class: no user_checker, entry_point, custom_authenticators, token_handler;
 * its doctrine.yaml no naming_strategy nor class_metadata_factory_name; its framework.yaml no error_controller; its
 * webauthn.yaml no repository.
 */
final class HostConfiguration
{
    public const FORM = 'form';
    public const OMNIAUTH = 'omniauth';

    /** The options' defaults: what a configuration that says nothing gives (the core's schema declares the same). */
    public const DEFAULTS = ['firewall' => 'main', 'rescue' => 'rescue', 'sign_in' => null, 'access_token' => true, 'provider' => 'users', 'role_hierarchy' => [], 'access_control' => []];

    public const USER_CHECKER = 'Base\Security\UserChecker';
    public const SIGN_IN = 'Base\Security\LoginFormAuthenticator';
    public const RESCUE = 'Base\Security\RescueFormAuthenticator';
    public const ACCESS_TOKENS = 'Base\Security\AccessTokenHandler';
    /** The visitors' firewall's token extractor: the Bearer tokens omnibase issued, and no other (a package's own). */
    public const ACCESS_TOKEN_EXTRACTOR = 'Omnibridge\Symfony\Omnibase\CoreAccessTokens';
    public const OAUTH_USERS = 'Base\Security\UserProvider';
    public const USER_PROVIDER = 'Base\Security\UserProvider';
    public const OMNIAUTH_AUTHENTICATOR = 'Omnibridge\Symfony\Auth\OmniauthAuthenticator';
    public const USERS = 'Base\Repository\Users';
    public const ERROR_CONTROLLER = 'Base\Controller\ErrorController::Main';
    public const NAMING_STRATEGY = 'Base\Database\Mapping\NamingStrategy';
    public const METADATA_FACTORY = 'Base\Database\Mapping\ClassMetadataFactory';
    /** omnibridge/doctrine's: Doctrine's own getRepository() answers its plain repository. */
    public const REPOSITORY_FACTORY = 'Omnibridge\Doctrine\Omnibase\RepositoryFactory';
    public const PASSKEYS = 'Base\Repository\User\PasskeyRepository';
    public const PASSKEY_USERS = 'Base\Security\WebauthnUserEntityRepository';

    /**
     * The options of `base.security` this class reads, in the node format of the core's schema (Base\Options): the
     * core declares them; a core that does not yet is completed with them (BaseConfiguration).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schema(): array
    {
        return [
            'firewall' => ['type' => 'scalar', 'default' => self::DEFAULTS['firewall'], 'info' => 'The visitors\' firewall, which omnibase gives its user checker, sign-in and entry point (null: none)'],
            'rescue' => ['type' => 'scalar', 'default' => self::DEFAULTS['rescue'], 'info' => 'The rescue sign-in\'s firewall (null: none)'],
            'sign_in' => ['type' => 'enum', 'values' => [null, self::FORM, self::OMNIAUTH], 'default' => null, 'info' => 'How visitors sign in: form (omnibase\'s sign-in form) or omniauth; null: omniauth where it is configured, else form'],
            'access_token' => ['type' => 'bool', 'default' => true, 'info' => 'Bearer tokens accepted on the visitors\' firewall'],
            'provider' => ['type' => 'scalar', 'default' => self::DEFAULTS['provider'], 'info' => 'The name of the user provider omnibase declares: the application\'s accounts'],
            'role_hierarchy' => ['type' => 'array', 'key' => 'role', 'prototype' => ['type' => 'array', 'prototype' => ['type' => 'scalar'], 'deep_merging' => false], 'default' => [], 'info' => 'The roles each role holds, read by every host (ROLE_ADMIN: [ROLE_USER])'],
            'access_control' => ['type' => 'array', 'prototype' => ['type' => 'array', 'children' => ['path' => ['type' => 'scalar'], 'roles' => ['type' => 'mixed']]], 'default' => [], 'info' => 'The pages kept for some accounts, by their path, read by every host ({ path: ^/admin, roles: ROLE_ADMIN })'],
        ];
    }

    /**
     * The options of base.security as the configurations loaded so far give them: the last one that names an option
     * wins, the defaults for the rest. Read before the configuration is processed (an extension prepends first).
     *
     * @return array{firewall: ?string, rescue: ?string, sign_in: ?string, access_token: bool, provider: string, role_hierarchy: array<string, mixed>, access_control: list<array<string, mixed>>}
     */
    public static function options(ContainerBuilder $builder): array
    {
        $options = self::DEFAULTS;
        foreach ($builder->getExtensionConfig('base') as $config) {
            foreach (\is_array($config['security'] ?? null) ? $config['security'] : [] as $name => $value) {
                if (\array_key_exists($name, $options)) {
                    $options[$name] = \is_string($value) ? $builder->resolveEnvPlaceholders($builder->getParameterBag()->resolveValue($value), true) : $value;
                }
            }
        }
        if (null === $options['sign_in']) {
            $options['sign_in'] = [] !== array_filter($builder->getExtensionConfig('omniauth')) ? self::OMNIAUTH : self::FORM; // as OmniauthExtension::load(): empty configs wire nothing
        }

        /** @var array{firewall: ?string, rescue: ?string, sign_in: ?string, access_token: bool, provider: string, role_hierarchy: array<string, mixed>, access_control: list<array<string, mixed>>} $options */
        return $options;
    }

    public static function prepend(ContainerBuilder $builder, bool $doctrine): void
    {
        $options = self::options($builder);
        if ($builder->hasExtension('security')) {
            if ([] !== $security = self::security($builder, $options, $doctrine)) {
                $builder->prependExtensionConfig('security', $security);
            }
        }
        if ($doctrine && $builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', ['orm' => self::orm($builder)]);
        }
        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', ['error_controller' => self::ERROR_CONTROLLER]);
        }
        if ($builder->hasExtension('webauthn')) {
            $builder->prependExtensionConfig('webauthn', ['credential_repository' => self::PASSKEYS, 'user_repository' => self::PASSKEY_USERS]);
        }
    }

    /**
     * Security's: the accounts' hasher and provider, and on the two firewalls the site declares under the names of
     * base.security, omnibase's user checker, authenticators and entry point. Every firewall of the site is named, in
     * the site's order: Security refuses a firewall the first configuration does not know, and tries them in order.
     *
     * @param array{firewall: ?string, rescue: ?string, sign_in: ?string, access_token: bool, provider: string, role_hierarchy: array<string, mixed>, access_control: list<array<string, mixed>>} $options
     *
     * @return array{}|array{password_hashers: array<string, array<string, string>>, providers: array<string, array<string, mixed>>, firewalls: array<string, array<string, mixed>>, role_hierarchy?: array<string, mixed>, access_control?: list<array<string, mixed>>}
     */
    public static function security(ContainerBuilder $builder, array $options, bool $doctrine): array
    {
        $declared = [];
        $providers = [];
        foreach ($builder->getExtensionConfig('security') as $config) {
            $providers += \is_array($config['providers'] ?? null) ? $config['providers'] : [];
            foreach (\is_array($config['firewalls'] ?? null) ? $config['firewalls'] : [] as $name => $firewall) {
                $declared[$name] = ($declared[$name] ?? []) + (\is_array($firewall) ? $firewall : []);
            }
        }
        if ([] === $declared) {
            return [];
        }

        $omniauth = self::OMNIAUTH === $options['sign_in'];
        $firewalls = [];
        foreach ($declared as $name => $firewall) {
            $firewalls[$name] = [];
            if (false === ($firewall['security'] ?? true)) {
                continue;
            }
            if ($name === $options['firewall']) {
                $firewalls[$name] = [
                    'user_checker' => self::USER_CHECKER,
                    'custom_authenticators' => $omniauth ? [self::OMNIAUTH_AUTHENTICATOR] : [self::RESCUE, self::SIGN_IN],
                    'entry_point' => self::SIGN_IN,
                ];
                if ($options['access_token']) {
                    $firewalls[$name]['access_token'] = ['token_handler' => self::ACCESS_TOKENS, 'token_extractors' => [self::ACCESS_TOKEN_EXTRACTOR]];
                }
                if (isset($firewall['oauth'])) {
                    $firewalls[$name]['oauth'] = ['oauth_user_provider' => ['service' => self::OAUTH_USERS]];
                }
            } elseif ($name === $options['rescue']) {
                $firewalls[$name] = [
                    'user_checker' => self::USER_CHECKER,
                    'custom_authenticators' => [$omniauth ? self::OMNIAUTH_AUTHENTICATOR : self::RESCUE],
                    'entry_point' => self::RESCUE,
                ];
            }
        }

        $security = [
            'password_hashers' => ['Base\Entity\User' => ['algorithm' => 'auto']],
            // The accounts' provider, unless the site declares one of that name itself (a second type for it is refused):
            // omnibase's own on every persistence (Base\Security\UserProvider: loadUserByIdentifier() through the agnostic
            // Base\Repository\Users). Not Doctrine's `entity` provider: Doctrine's getRepository() is its plain repository,
            // which loads no user by identifier.
            'providers' => isset($providers[$options['provider']]) ? [] : [$options['provider'] => ['id' => self::USER_PROVIDER]],
            'firewalls' => $firewalls,
        ];
        if ([] !== $options['role_hierarchy']) {
            $security['role_hierarchy'] = $options['role_hierarchy'];
        }
        if ([] !== $options['access_control']) {
            $security['access_control'] = $options['access_control'];
        }

        return $security;
    }

    /** base.router.fallback: the service answering the route names this host lacks, given to the Urls and Routes adapters. */
    public static function routerFallback(ContainerBuilder $container, string $service): void
    {
        foreach (['Omnibridge\Symfony\RouterUrls', 'Omnibridge\Symfony\Omnibase\RouterRoutes'] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setArgument('$elsewhere', new \Symfony\Component\DependencyInjection\Reference($service));
            }
        }
    }

    /**
     * At boot: the repositories UnitOfWorks::repositories() gives on this container's entity managers (a fixtures
     * command's, a lifecycle listener's) are the container's registry - the application's repositories its services, a
     * repository's own dependencies (a cache pool) given -, not a registry built without them.
     */
    public static function unitOfWorkRepositories(\Symfony\Component\DependencyInjection\ContainerInterface $container): void
    {
        if (!class_exists(\Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours::class) || !$container->has('doctrine') || !$container->has(\Base\Database\Repository\Repositories::class)) {
            return;
        }
        \Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours::host(static function (\Doctrine\ORM\EntityManagerInterface $entityManager) use ($container): ?\Base\Database\Repository\Repositories {
            $repositories = \in_array($entityManager, $container->get('doctrine')->getManagers(), true) ? $container->get(\Base\Database\Repository\Repositories::class) : null;

            return $repositories instanceof \Base\Database\Repository\Repositories ? $repositories : null;
        });
    }

    /** The tag of the repositories the registry's locator holds (Repositories::of(), config/omnibase/services/database.php). */
    public const REPOSITORY_TAG = 'base.repository';

    /**
     * Every repository the container autoconfigures - the application's App\Repository\* (its services.yaml's `App\:`
     * resource), a bundle's - in the registry's locator: Repositories::of() answers that service, its own constructor's
     * services injected (a cache pool), instead of building one without them.
     */
    public static function repositories(ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(\Base\Database\Repository\ServiceEntityRepository::class)->addTag(self::REPOSITORY_TAG);
    }

    /**
     * Doctrine's: omnibase's naming strategy, metadata factory and repository factory, on each entity manager the site declares (the
     * default one's shortcut where it declares none).
     *
     * @return array{naming_strategy: string, class_metadata_factory_name: string, repository_factory: string}|array{entity_managers: array<string, array{naming_strategy: string, class_metadata_factory_name: string, repository_factory: string}>}
     */
    public static function orm(ContainerBuilder $builder): array
    {
        $mapping = ['naming_strategy' => self::NAMING_STRATEGY, 'class_metadata_factory_name' => self::METADATA_FACTORY, 'repository_factory' => self::REPOSITORY_FACTORY];
        $managers = [];
        foreach ($builder->getExtensionConfig('doctrine') as $config) {
            foreach (\is_array($config['orm']['entity_managers'] ?? null) ? $config['orm']['entity_managers'] : [] as $name => $_) {
                $managers[$name] = $mapping;
            }
        }

        return [] === $managers ? $mapping : ['entity_managers' => $managers];
    }
}
