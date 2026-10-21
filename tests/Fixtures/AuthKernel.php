<?php

namespace Omnibridge\Symfony\Tests\Fixtures;

use Omnibridge\Symfony\Auth\Contexts;
use Omnibridge\Symfony\Auth\OmniauthAuthenticator;
use Omnibridge\Symfony\OmnibridgeBundle;
use Omnibridge\Symfony\Tests\Fixtures\Auth\TotpSecrets;
use Omnibridge\Symfony\Tests\Fixtures\Auth\Users;
use Omniauth\Factor;
use Omniauth\OAuth\Testing\FakeProvider;
use Omniauth\Omniauth;
use Omniauth\Passkey\Testing\InMemoryPasskeys;
use Omniauth\Testing\RecordingSender;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * A Symfony application signing in through omniauth alone: FrameworkBundle,
 * SecurityBundle (one firewall, OmniauthAuthenticator its authenticator and
 * entry point, remember-me, sign-out), TwigBundle (the second factor's page)
 * and OmnibridgeBundle, every method of omniauth.yaml on, the accounts in
 * memory (Auth\Users), an identity provider simulated (omniauth/oauth's
 * FakeProvider), the e-mails recorded (RecordingSender). With $omniauth
 * false, no omniauth.yaml: the same application without omniauth's sign-in.
 */
final class AuthKernel extends Kernel
{
    use MicroKernelTrait;

    public const SECRET = 'omnibridge-auth-test-kernel-secret-0123456789abcdef';

    public function __construct(string $environment = 'test', bool $debug = true, private readonly bool $omniauth = true)
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new OmnibridgeBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/auth-'.$this->environment.($this->omniauth ? '' : '-without');
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => self::SECRET,
            'test' => true,
            'router' => ['utf8' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => true,
            'cache' => ['pools' => ['omniauth.test_pool' => ['adapter' => 'cache.adapter.filesystem']]],
        ]);
        $container->extension('twig', ['default_path' => __DIR__.'/Auth/templates']);
        $container->extension('security', [
            'providers' => ['users' => ['id' => Users::class]],
            'firewalls' => ['main' => [
                'lazy' => true,
                'provider' => 'users',
                'custom_authenticators' => $this->omniauth ? [OmniauthAuthenticator::class] : [],
                'entry_point' => $this->omniauth ? OmniauthAuthenticator::class : null,
                'remember_me' => ['secret' => self::SECRET, 'lifetime' => 604800],
                'logout' => ['path' => 'logout', 'target' => '/'],
            ]],
            'access_control' => [
                ['path' => '^/login/2fa', 'roles' => 'IS_AUTHENTICATED_2FA_IN_PROGRESS'],
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/member', 'roles' => 'ROLE_USER'],
            ],
        ]);

        $services = $container->services();
        $services->set(Users::class)->public();
        $services->alias('Omniauth\Contract\Accounts', Users::class);
        $services->set(RecordingSender::class)->public();
        $services->set(TotpSecrets::class);
        $services->set(InMemoryPasskeys::class)->args([service(Users::class)])->public();
        // A return path: the host of each request completes it.
        $services->set('test.google', FakeProvider::class)->args(['google', '/connect/google/check'])->public();

        if (!$this->omniauth) {
            return;
        }
        $container->extension('omniauth', [
            'secret' => self::SECRET,
            'stores' => [
                'accounts' => Users::class,
                'passwords' => Users::class,
                'link_tokens' => Users::class,
                'email_codes' => Users::class,
                'backup_codes' => Users::class,
                'trusted_version' => Users::class,
                'identities' => Users::class,
                'totp_secrets' => TotpSecrets::class,
                'passkeys' => InMemoryPasskeys::class,
                'sender' => RecordingSender::class,
            ],
            'password' => ['routes' => ['login']],
            'link' => ['route' => 'link'],
            'email_code' => null,
            'backup_codes' => null,
            'trusted_device' => null,
            'totp' => null,
            'passkey' => null,
            'oauth' => ['providers' => [
                'google' => ['service' => 'test.google'],
                // Google by configuration (omniauth/google's GoogleFactory), its return address the check route's path.
                'workspace' => ['type' => 'google', 'client_id' => 'workspace.apps.googleusercontent.com', 'client_secret' => 'secret'],
            ]],
            'throttle' => ['pool' => 'omniauth.test_pool'],
            'login_route' => 'login',
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller('kernel::page');
        $routes->add('member', '/member')->controller('kernel::page');
        $routes->add('login', '/login')->controller('kernel::login');
        $routes->add('logout', '/logout')->controller('kernel::page');
        $routes->add('link_request', '/link')->controller('kernel::requestLink')->methods(['POST']);
        $routes->add('link', '/link/{token}')->controller('kernel::link')->defaults(['token' => null]);
        if ($this->omniauth) {
            $routes->import('.', 'omniauth');
        }
    }

    public function page(Request $request): Response
    {
        return new Response('page '.$request->getPathInfo());
    }

    /** The sign-in page: its form's CSRF token, and the error left by a refused attempt. */
    public function login(Request $request): Response
    {
        $error = $request->getSession()->get('_security.last_error');
        $token = $this->getContainer()->get('test.csrf');
        \assert($token instanceof CsrfTokenManagerInterface);

        return new Response(json_encode([
            'csrf' => $token->getToken('authenticate')->getValue(),
            'error' => $error?->getMessageKey(),
        ]));
    }

    /** The sign-in link's request: omniauth's link ceremony, the same answer for every address. */
    public function requestLink(Request $request): Response
    {
        $omniauth = $this->getContainer()->get('test.omniauth');
        $challenge = $omniauth->begin(Factor::Link, $this->getContainer()->get('test.contexts')->of($request), ['identifier' => (string) $request->request->get('email')]);

        return new Response($challenge->kind);
    }

    /** The link's page: reached when no link signed in (none given, or one refused). */
    public function link(Request $request, ?string $token = null): Response
    {
        return new Response('link page, token '.var_export($token, true));
    }

    protected function build(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
    {
        $container->setAlias('test.csrf', 'security.csrf.token_manager')->setPublic(true);
        if ($this->omniauth) {
            $container->setAlias('test.omniauth', Omniauth::class)->setPublic(true);
            $container->setAlias('test.contexts', Contexts::class)->setPublic(true);
        }
    }
}
