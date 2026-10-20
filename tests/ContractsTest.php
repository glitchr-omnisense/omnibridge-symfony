<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Contract\Access;
use Omnibridge\Contract\Environment;
use Omnibridge\Contract\Sessions;
use Omnibridge\Contract\Tokens;
use Omnibridge\Contract\Translations;
use Omnibridge\Contract\Urls;
use Omnibridge\Runtime;
use Omnibridge\Symfony\Contract\CsrfTokens;
use Omnibridge\Symfony\Contract\KernelEnvironment;
use Omnibridge\Symfony\Contract\SecurityAccess;
use Omnibridge\Symfony\Contract\SecuritySessions;
use Omnibridge\Symfony\DependencyInjection\ContractsPass;
use Omnibridge\Symfony\RouterUrls;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;
use Omnibridge\Symfony\TranslatorTranslations;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Each contract of omniscient/omnibridge answered by Symfony, in a kernel of
 * FrameworkBundle, SecurityBundle and OmnibridgeBundle: one object through
 * Omnibridge\Runtime and through the container, and the answers of Symfony's
 * own services - the router's addresses, the translator's messages, the
 * authorization checker, the token storage, the CSRF manager, the kernel's
 * parameters.
 */
final class ContractsTest extends KernelTestCase
{
    /** @var array<class-string, class-string> */
    private const ADAPTERS = [
        Urls::class => RouterUrls::class,
        Translations::class => TranslatorTranslations::class,
        Environment::class => KernelEnvironment::class,
        Access::class => SecurityAccess::class,
        Sessions::class => SecuritySessions::class,
        Tokens::class => CsrfTokens::class,
    ];

    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Runtime::reset();
    }

    public function testTheBundleSeedsTheRegistryWithEachAdapter(): void
    {
        $locator = static::getContainer()->get(ContractsPass::SERVICE);
        self::assertSame($locator, Runtime::getContainer());

        foreach (self::ADAPTERS as $contract => $adapter) {
            self::assertInstanceOf($adapter, Runtime::get($contract), $contract);
            self::assertSame($locator->get($contract), Runtime::get($contract), $contract);
            self::assertSame(static::getContainer()->get($contract), Runtime::get($contract), $contract.', by the container');
        }
        self::assertSame(static::getContainer()->get('event_dispatcher'), Runtime::events());
        self::assertInstanceOf(EventDispatcherInterface::class, Runtime::events());
    }

    public function testShutdownTakesTheRegistryBack(): void
    {
        self::assertNotNull(Runtime::getContainer());
        self::ensureKernelShutdown();
        self::assertNull(Runtime::getContainer());
    }

    public function testUrlsAreTheRoutersAddresses(): void
    {
        $urls = Runtime::urls();
        self::assertSame('/page/fuji', $urls->path('page', ['slug' => 'fuji']));
        self::assertSame('http://localhost/page/fuji', $urls->url('page', ['slug' => 'fuji']));
        self::assertSame('/', $urls->path('home'));
        self::assertSame('build/app.css', $urls->asset('build/app.css'), 'no asset packages: the path as it is');
        self::assertSame('page/fuji', $urls->generate('page', ['slug' => 'fuji'], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::RELATIVE_PATH));
    }

    public function testTranslationsAreTheTranslatorsMessages(): void
    {
        $translations = Runtime::translations();
        self::assertSame('Hello Ada', $translations->trans('hello', ['name' => 'Ada']));
        self::assertSame('Bonjour Ada', $translations->trans('hello', ['name' => 'Ada'], null, 'fr'));
        self::assertTrue($translations->exists('hello'));
        self::assertTrue($translations->exists('@messages.hello'), '"@domain.key" read as the key of its domain');
        self::assertFalse($translations->exists('goodbye'));
    }

    public function testEnvironmentIsTheKernels(): void
    {
        $environment = Runtime::environment();
        self::assertSame('test', $environment->name());
        self::assertTrue($environment->isDebug());
        self::assertSame('omnibridge-secret', $environment->secret());
    }

    public function testAccessIsTheAuthorizationCheckersAndSessionsSignTheVisitorOut(): void
    {
        $access = Runtime::access();
        self::assertNull($access->getUser());
        self::assertFalse($access->isGranted('ROLE_USER'));

        $tokens = static::getContainer()->get('security.token_storage');
        $ada = new InMemoryUser('ada', 'x', ['ROLE_ADMIN']);
        $tokens->setToken(new UsernamePasswordToken($ada, 'main', $ada->getRoles()));
        self::assertSame($ada, $access->getUser());
        self::assertTrue($access->isGranted('ROLE_USER'), 'the role hierarchy');

        // Someone else is not signed out; the visitor is (the cookies cleared, headers already sent here).
        self::assertFalse(Runtime::sessions()->signOut(new InMemoryUser('bob', 'x')));
        self::assertSame($ada, $access->getUser());
        self::assertTrue(@Runtime::sessions()->signOut($ada));
        self::assertNull($tokens->getToken());
    }

    public function testTheRememberMeDomainIsAskedAtSignOut(): void
    {
        $storage = new \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage();
        $ada = new InMemoryUser('ada', 'x');
        $storage->setToken(new UsernamePasswordToken($ada, 'main'));
        $asked = 0;
        $sessions = new SecuritySessions($storage, function () use (&$asked): string {
            ++$asked;

            return 'example.org';
        });

        self::assertSame(0, $asked, 'not before');
        self::assertTrue(@$sessions->signOut($ada));
        self::assertSame(1, $asked);
    }

    public function testTokensAreTheCsrfManagers(): void
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);

        $manager = static::getContainer()->get('security.csrf.token_manager');
        $token = Runtime::tokens()->csrf('comment');
        self::assertNotSame('', $token);
        self::assertTrue($manager->isTokenValid(new CsrfToken('comment', $token)));
        // Masked anew at each call (BREACH), the same secret: both valid for the intent, not for another.
        self::assertTrue($manager->isTokenValid(new CsrfToken('comment', Runtime::tokens()->csrf('comment'))));
        self::assertFalse($manager->isTokenValid(new CsrfToken('vote', $token)));
        // The contract's check is the manager's: valid for its intent only, never empty nor missing.
        self::assertTrue(Runtime::tokens()->isValid('comment', $token));
        self::assertFalse(Runtime::tokens()->isValid('vote', $token));
        self::assertFalse(Runtime::tokens()->isValid('comment', 'forged'));
        self::assertFalse(Runtime::tokens()->isValid('comment', ''));
        self::assertFalse(Runtime::tokens()->isValid('comment', null));
    }

    public function testWithoutSecurityTheContainerCompilesWithoutAccessSessionsAndTokens(): void
    {
        self::ensureKernelShutdown();
        $kernel = new TestKernel('test', true, security: false);
        $kernel->boot();
        try {
            $container = $kernel->getContainer();
            self::assertSame($container->get(ContractsPass::SERVICE), Runtime::getContainer());
            self::assertInstanceOf(RouterUrls::class, Runtime::urls());
            self::assertInstanceOf(TranslatorTranslations::class, Runtime::translations());
            self::assertInstanceOf(KernelEnvironment::class, Runtime::environment());
            foreach ([Access::class, Sessions::class, Tokens::class] as $contract) {
                self::assertFalse(Runtime::has($contract), $contract);
                self::assertFalse($container->has($contract), $contract);
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
