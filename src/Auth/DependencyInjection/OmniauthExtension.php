<?php

namespace Omnibridge\Symfony\Auth\DependencyInjection;

use Omnibridge\Contract\Tokens;
use Omnibridge\Symfony\Auth\AuthRouteLoader;
use Omnibridge\Symfony\Auth\CeremonyController;
use Omnibridge\Symfony\Auth\Contexts;
use Omnibridge\Symfony\Auth\OAuthProviders;
use Omnibridge\Symfony\Auth\OmniauthAuthenticator;
use Omnibridge\Symfony\Auth\PendingTrustResolver;
use Omnibridge\Symfony\Auth\PendingVoter;
use Omnibridge\Symfony\Auth\SecondFactorGate;
use Omnibridge\Symfony\Auth\SessionState;
use Omniauth\BackupCode\BackupCodeMethod;
use Omniauth\CacheThrottle;
use Omniauth\Contract\SecondFactorPolicy;
use Omniauth\Contract\State;
use Omniauth\Contract\Throttle;
use Omniauth\EmailCode\EmailCodeMethod;
use Omniauth\EnrolledSecondFactors;
use Omniauth\Factor;
use Omniauth\Google\GoogleFactory;
use Omniauth\Link\LinkMethod;
use Omniauth\Method\Methods;
use Omniauth\OAuth\NoSecondFactorLinking;
use Omniauth\OAuth\OAuthMethod;
use Omniauth\OAuth\Provider\ProviderFactory;
use Omniauth\OAuth\Provider\Providers;
use Omniauth\Omniauth;
use Omniauth\Passkey\PasskeyMethod;
use Omniauth\Passkey\RelyingParty;
use Omniauth\Password\PasswordMethod;
use Omniauth\Totp\CacheUsedSteps;
use Omniauth\Totp\TotpMethod;
use Omniauth\TrustedDevice\TrustedDevices;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * omniauth wired into a Symfony application (plan "omniauth", §3.1, step AU6),
 * from config/packages/omniauth.yaml: the conductor (Omniauth\Omniauth) with
 * the methods present - each key turns its method on; passkeys, TOTP and
 * identity providers need their package (omniauth/passkey, omniauth/totp,
 * omniauth/oauth) -, the second-factor rule (EnrolledSecondFactors, which an
 * application decorates: Omniauth\Contract\SecondFactorPolicy), the session
 * as its State, a PSR-6 pool as its Throttle, Symfony's dispatcher for its
 * events; and around it, the authenticator a firewall names
 * (custom_authenticator: Omnibridge\Symfony\Auth\OmniauthAuthenticator), the
 * second factor's token, voter, trust resolver and gate, the ceremonies'
 * controller and routes (type "omniauth").
 *
 * The stores are the application's: each contract by its own name, or the
 * service omniauth.yaml's "stores" names (omnibase binds its Base\Auth\ ones).
 *
 * Registered by OmnibridgeBundle, and by an application's own bundle with
 * register() (omnibase's BaseBundle), only where omniauth is installed; with
 * no omniauth.yaml (or one without any key), it loads nothing.
 */
final class OmniauthExtension extends Extension
{
    public const METHOD = 'omniauth.method';
    public const SECOND_FACTOR = 'omniauth.second_factor';
    public const POLICY = 'omniauth.policy.default';
    public const PROVIDERS = 'omniauth.oauth.providers';
    public const PROVIDER_FACTORY = 'omniauth.oauth_provider_factory';

    /** Registers this extension in a container being built, when omniauth is installed. */
    public static function register(ContainerBuilder $container): void
    {
        if (class_exists(Omniauth::class) && !$container->hasExtension('omniauth')) {
            $container->registerExtension(new self());
        }
    }

    public function getAlias(): string
    {
        return 'omniauth';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // Symfony loads every registered extension; with no omniauth.yaml (nor any key in it), nothing is wired.
        if ([] === array_filter($configs)) {
            return;
        }
        $config = $this->processConfiguration(new Configuration(), $configs);
        $stores = $config['stores'];
        $store = static fn (string $name) => new Reference($stores[$name]);
        $clock = new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $pool = new Reference($config['throttle']['pool'] ?? 'cache.app');

        $method = function (string $id, string $class, array $arguments, bool $second = false) use ($container): void {
            $definition = $container->register($id, $class)->setArguments($arguments)->addTag(self::METHOD);
            if ($second) {
                $definition->addTag(self::SECOND_FACTOR);
            }
        };

        // The methods.
        if ($config['password']['enabled']) {
            $hasher = null !== $config['password']['user_class']
                ? (new Definition(\Symfony\Component\PasswordHasher\PasswordHasherInterface::class))
                    ->setFactory([new Reference('security.password_hasher_factory'), 'getPasswordHasher'])
                    ->setArguments([$config['password']['user_class']])
                : null;
            $method('omniauth.method.password', PasswordMethod::class, [$store('accounts'), $store('passwords'), $hasher]);
        }
        if ($config['link']['enabled']) {
            $method('omniauth.method.link', LinkMethod::class, [$store('accounts'), $store('link_tokens'), $store('sender'), $config['link']['lifetime'], $clock]);
        }
        if ($config['email_code']['enabled']) {
            $method('omniauth.method.email_code', EmailCodeMethod::class, [$store('accounts'), $store('email_codes'), $store('sender'), $config['secret'], $config['email_code']['digits'], $config['email_code']['lifetime'], $clock], true);
        }
        if ($config['backup_codes']['enabled']) {
            $method('omniauth.method.backup_codes', BackupCodeMethod::class, [$store('backup_codes'), $config['backup_codes']['count']], true);
        }
        if ($config['trusted_device']['enabled']) {
            $method('omniauth.method.trusted_device', TrustedDevices::class, [$store('accounts'), $store('trusted_version'), $config['secret'], $config['trusted_device']['lifetime'], $clock]);
        }
        if ($config['totp']['enabled']) {
            self::needs(TotpMethod::class, 'omniauth/totp', 'totp');
            $totp = $config['totp'];
            $method('omniauth.method.totp', TotpMethod::class, [$store('accounts'), $store('totp_secrets'), new Definition(CacheUsedSteps::class, [$pool]), $totp['leeway'], $totp['period'], $totp['digits'], TotpMethod::ALGORITHM, $totp['issuer'], $totp['server_name'], $clock], true);
        }
        if ($config['passkey']['enabled']) {
            self::needs(PasskeyMethod::class, 'omniauth/passkey', 'passkey');
            $passkey = $config['passkey'];
            $method('omniauth.method.passkey', PasskeyMethod::class, [$store('accounts'), $store('passkeys'), new Definition(RelyingParty::class, [$passkey['rp_id'] ?: null, $passkey['rp_name'] ?: null, array_values($passkey['origins'])]), $passkey['timeout']]);
        }

        // The second factors alone, for the rule and the address linking: no cycle through the first factors.
        $container->register('omniauth.second_factors', Methods::class)->setArguments([new TaggedIteratorArgument(self::SECOND_FACTOR)]);

        $providers = [];
        if ($config['oauth']['enabled']) {
            self::needs(OAuthMethod::class, 'omniauth/oauth', 'oauth');
            foreach ($config['oauth']['providers'] as $name => $provider) {
                $provider['start_route'] ??= 'oauth_'.$name;
                $provider['start_path'] ??= '/connect/'.$name;
                $provider['check_route'] ??= 'oauth_'.$name.'_check';
                $provider['check_path'] ??= '/connect/'.$name.'/check';
                $providers[$name] = $provider;
            }
            $services = [];
            foreach ($providers as $provider) {
                if (null !== $provider['service']) {
                    $services[$provider['service']] = new Reference($provider['service']);
                }
            }
            // Each provider package's factory (omniauth/google's when it is installed; any other by its tag).
            $container->registerForAutoconfiguration(ProviderFactory::class)->addTag(self::PROVIDER_FACTORY);
            if (class_exists(GoogleFactory::class)) {
                $container->register('omniauth.oauth.factory.google', GoogleFactory::class)->setArguments([$clock])->addTag(self::PROVIDER_FACTORY);
            }
            $container->register(self::PROVIDERS, Providers::class)
                ->setFactory([OAuthProviders::class, 'create'])
                ->setArguments([$providers, new Reference('router'), new ServiceLocatorArgument($services), new TaggedIteratorArgument(self::PROVIDER_FACTORY)])
                ->setPublic(true);
            $byEmail = $config['oauth']['link_by_email'];
            $linking = match (true) {
                null === $byEmail => new Definition(NoSecondFactorLinking::class, [new Reference('omniauth.second_factors')]),
                \is_bool($byEmail), \in_array($byEmail, ['true', 'false'], true) => filter_var($byEmail, \FILTER_VALIDATE_BOOL),
                default => new Reference((string) $byEmail),
            };
            $method('omniauth.method.oauth', OAuthMethod::class, [$store('accounts'), $store('identities'), new Reference(self::PROVIDERS), $linking, $clock]);
        }

        $container->register('omniauth.methods', Methods::class)->setArguments([new TaggedIteratorArgument(self::METHOD)]);
        $container->setAlias(Methods::class, 'omniauth.methods');

        // The rule, the host's duties, the conductor.
        $container->register(self::POLICY, EnrolledSecondFactors::class)
            ->setArguments([new Reference('omniauth.second_factors'), array_map(static fn (string $f) => Factor::from($f), $config['second_factor']['after'])]);
        $container->setAlias(SecondFactorPolicy::class, self::POLICY);
        $container->register(SessionState::class, SessionState::class)->setArguments([new Reference('request_stack')]);
        $container->setAlias(State::class, SessionState::class);
        if ($config['throttle']['enabled']) {
            $limits = array_map(static fn (array $l) => [(int) ($l[0] ?? 5), (int) ($l[1] ?? 60)], $config['throttle']['limits']);
            $container->register(CacheThrottle::class, CacheThrottle::class)->setArguments([$pool, $limits, $clock]);
            $container->setAlias(Throttle::class, CacheThrottle::class);
        }
        $container->register(Omniauth::class, Omniauth::class)->setArguments([
            $store('accounts'),
            new Reference('omniauth.methods'),
            new Reference(State::class),
            new Reference(SecondFactorPolicy::class),
            $config['throttle']['enabled'] ? new Reference(Throttle::class) : null,
            new Reference('event_dispatcher'),
            $clock,
        ])->setPublic(true);

        // Symfony's side.
        $runtime = self::runtime($config, $providers);
        $container->register(Contexts::class, Contexts::class)->setArguments([
            new Reference('request_stack'),
            new Reference('security.firewall.map', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $config['trusted_device']['cookie'],
            $config['trusted_device']['firewall'],
        ]);
        $container->register(OmniauthAuthenticator::class, OmniauthAuthenticator::class)->setArguments([
            new Reference(Omniauth::class),
            new Reference(Contexts::class),
            new Reference('router'),
            new Reference('security.token_storage'),
            new Reference(Tokens::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $runtime,
        ]);
        $container->register(PendingVoter::class, PendingVoter::class)->addTag('security.voter');
        $container->register(PendingTrustResolver::class, PendingTrustResolver::class)
            ->setDecoratedService('security.authentication.trust_resolver', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
            ->setArguments([new Reference(PendingTrustResolver::class.'.inner')]);
        $container->register(SecondFactorGate::class, SecondFactorGate::class)->setArguments([
            new Reference('security.token_storage'),
            new Reference('router'),
            $runtime['second_factor']['form_route'],
            [$runtime['second_factor']['form_route'], $runtime['second_factor']['check_route']],
            new Reference('security.access_map', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('security.access.decision_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('security.logout_url_generator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])->addTag('kernel.event_subscriber');
        $container->register(CeremonyController::class, CeremonyController::class)->setArguments([
            new Reference(Omniauth::class),
            new Reference(Contexts::class),
            new Reference('router'),
            new Reference('security.token_storage'),
            $runtime,
            new Reference('twig', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('security.logout_url_generator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ])->setPublic(true)->addTag('controller.service_arguments');
        $container->register(AuthRouteLoader::class, AuthRouteLoader::class)
            ->setArguments([$runtime, '%kernel.environment%'])
            ->addTag('routing.loader');
    }

    /**
     * What the authenticator, the controller and the routes read, resolved.
     *
     * @param array<string, mixed>                $config
     * @param array<string, array<string, mixed>> $providers
     *
     * @return array<string, mixed>
     */
    private static function runtime(array $config, array $providers): array
    {
        $csrf = static fn (array $c) => $c['enabled'] ? ['field' => $c['field'], 'intent' => $c['intent']] : null;
        $password = $config['password'];

        return [
            'login_route' => $config['login_route'],
            'sign_in' => [
                'routes' => $password['enabled'] ? array_values($password['routes']) : [],
                'form' => $password['form'],
                'identifier' => $password['identifier'],
                'password' => $password['password'],
                'remember' => $password['remember'],
                'target_path_parameter' => $password['target_path_parameter'],
                'default_target_path' => $password['default_target_path'],
                'csrf' => $csrf($password['csrf']),
            ],
            'link' => $config['link']['enabled'] && null !== $config['link']['route'] ? ['route' => $config['link']['route'], 'parameter' => $config['link']['parameter']] : null,
            'passkey' => $config['passkey']['enabled'] ? array_intersect_key($config['passkey'], array_flip(['options_path', 'options_route', 'path', 'route', 'remember'])) : null,
            'second_factor' => array_intersect_key($config['second_factor'], array_flip(['form_path', 'form_route', 'check_path', 'check_route', 'template', 'code', 'trusted'])) + [
                'csrf' => $csrf($config['second_factor']['csrf']),
                'digits' => ['totp' => $config['totp']['digits'] ?? 6, 'email-code' => $config['email_code']['digits'] ?? 6],
            ],
            'oauth' => [
                'routes' => array_combine(array_column($providers, 'check_route'), array_keys($providers)) ?: [],
                'providers' => array_map(static fn (array $p) => array_intersect_key($p, array_flip(['start_path', 'start_route', 'check_path', 'check_route'])), $providers),
                'sign_up_route' => $config['oauth']['sign_up_route'] ?? null,
            ],
            'trusted_device' => ['cookie' => $config['trusted_device']['cookie'], 'lifetime' => $config['trusted_device']['lifetime']],
            'messages' => $config['messages'],
        ];
    }

    private static function needs(string $class, string $package, string $key): void
    {
        if (!class_exists($class)) {
            throw new \LogicException(\sprintf('omniauth.yaml turns "%s" on, which needs %s: composer require %s.', $key, $package, $package));
        }
    }
}
