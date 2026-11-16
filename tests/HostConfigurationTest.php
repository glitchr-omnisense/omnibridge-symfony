<?php

namespace Omnibridge\Symfony\Tests;

use Base\DependencyInjection\HostConfiguration;
use Omnibridge\Symfony\Tests\Fixtures\Host\HostKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * A site's configuration names no class: an application whose security, doctrine and framework configuration say
 * only what is its own (tests/Fixtures/Host/HostKernel) boots with omnibase's user checker, sign-in, entry points,
 * token handler, users' provider, naming strategy, metadata factory and error controller, wired from `base:`.
 */
final class HostConfigurationTest extends TestCase
{
    /** @var list<HostKernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
            (new Filesystem())->remove($kernel->getCacheDir());
        }
        $this->kernels = [];
        restore_exception_handler();
    }

    /** @param array<string, mixed> $base */
    private function boot(array $base = [], string $name = 'default'): HostKernel
    {
        $kernel = new HostKernel($base, $name);
        (new Filesystem())->remove($kernel->getCacheDir());
        $kernel->boot();

        return $this->kernels[] = $kernel;
    }

    /** @return array<array-key, mixed> a parameter HostKernel recorded */
    private static function ids(\Symfony\Component\DependencyInjection\ContainerInterface $container, string $parameter): array
    {
        $value = $container->getParameter($parameter);
        self::assertIsArray($value);

        return $value;
    }

    public function testTheFirewallsAreGivenOmnibasesSignInFromBase(): void
    {
        $container = $this->boot()->getContainer();

        $main = self::ids($container, 'host_test.firewall.main');
        self::assertContains(HostConfiguration::USER_CHECKER, $main);
        self::assertContains(HostConfiguration::SIGN_IN, $main);
        self::assertContains(HostConfiguration::RESCUE, $main, 'the rescue form signs in on the visitors\' firewall too');
        self::assertContains('access_token', $main);

        $rescue = self::ids($container, 'host_test.firewall.rescue');
        self::assertContains(HostConfiguration::USER_CHECKER, $rescue);
        self::assertContains(HostConfiguration::RESCUE, $rescue);
        self::assertNotContains(HostConfiguration::SIGN_IN, $rescue);

        self::assertNotContains(HostConfiguration::USER_CHECKER, self::ids($container, 'host_test.firewall.dev'), 'a firewall without security is left alone');
        self::assertSame([], self::ids($container, 'host_test.providers'), 'no provider of Doctrine\'s (an `entity` one would be security.user.provider.concrete.users)');
        self::assertContains(HostConfiguration::USER_PROVIDER, $main, 'omnibase\'s provider on the visitors\' firewall');
    }

    /**
     * Signing in on a Doctrine host: the visitors' firewall's provider is omnibase's (Base\Security\UserProvider, its
     * stand-in here), never Doctrine's `entity` provider, whose plain repository loads no user by identifier.
     */
    public function testAnAccountSignsInOnADoctrineHost(): void
    {
        $kernel = $this->boot([], 'sign-in');

        $signedIn = $kernel->handle(Request::create('/sign-in?user=ada'));
        self::assertSame('signed in: ada', $signedIn->getContent());
        $unknown = $kernel->handle(Request::create('/sign-in?user=nobody'));
        self::assertSame('refused: nobody', $unknown->getContent());
    }

    /**
     * The application's repository with a service of its own (a cache pool) is the one the registry answers: autowired
     * as a site's `App\:` resource makes it, in the registry's locator (base.repository, autoconfigured).
     */
    public function testTheRegistryAnswersTheApplicationsRepositoryService(): void
    {
        if (!class_exists(\Base\Database\Repository\EntityRepositories::class) || !class_exists(\Omnibridge\Doctrine\Omnibase\DoctrineFinders::class)) {
            self::markTestSkipped('omnisense/omnibase and omnibridge/doctrine are not installed.');
        }
        $registry = $this->boot([], 'repositories')->getContainer()->get(\Base\Database\Repository\EntityRepositories::class);
        self::assertInstanceOf(\Base\Database\Repository\EntityRepositories::class, $registry);
        $repository = $registry->of(\App\Entity\Note::class);

        self::assertInstanceOf(\App\Repository\NoteRepository::class, $repository);
        self::assertInstanceOf(\Symfony\Contracts\Cache\CacheInterface::class, $repository->cache);
    }

    /**
     * The visitors' firewall authenticates omnibase's Bearer tokens only: another package's (omnibase/forge's gla_ hub
     * tokens) goes on unauthenticated by it - no 401 - for its own handling; a token of omnibase's that it refuses is
     * still refused.
     */
    public function testOnlyOmnibasesBearerTokensAreClaimed(): void
    {
        $kernel = $this->boot([], 'bearer');
        $bearer = static fn (string $token): Request => Request::create('/whoami', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertSame('ada', $kernel->handle($bearer('core-token'))->getContent(), 'omnibase\'s token signs in');
        $foreign = $kernel->handle($bearer('gla_0123456789abcdef'));
        self::assertSame(200, $foreign->getStatusCode(), 'a foreign token is not refused by omnibase');
        self::assertSame('anonymous', $foreign->getContent());
        self::assertSame('anonymous', $kernel->handle(Request::create('/whoami'))->getContent());
    }

    /**
     * The registry a fixtures command or a lifecycle listener reaches - UnitOfWorks::repositories() on the entity
     * manager's unit of work, not the container's service - answers the application's ThreadRepository subclass with its
     * own service (lesringostars' CommentRepository and its cache pool), as the container's registry does.
     */
    public function testTheUnitOfWorksRegistryAnswersTheApplicationsRepositoryService(): void
    {
        if (!class_exists(\Base\Database\Repository\EntityRepositories::class) || !class_exists(\Omnibridge\Doctrine\Omnibase\DoctrineFinders::class)) {
            self::markTestSkipped('omnisense/omnibase and omnibridge/doctrine are not installed.');
        }
        \Base\Service\Model\ClassAliases::register();
        $container = $this->boot([], 'unit-of-work')->getContainer();
        $doctrine = $container->get('doctrine');
        self::assertInstanceOf(\Doctrine\Persistence\ManagerRegistry::class, $doctrine);
        $unitOfWork = \Base\Database\UnitOfWorks::of($doctrine->getManager());

        /** @var \Base\Database\Repository\Repositories $registry */
        $registry = \Base\Database\UnitOfWorks::repositories($unitOfWork);
        $repository = $registry->of(\App\Entity\Comment::class);

        self::assertInstanceOf(\App\Repository\CommentRepository::class, $repository);
        self::assertInstanceOf(\Base\Repository\ThreadRepository::class, $repository);
        self::assertInstanceOf(\Symfony\Contracts\Cache\CacheInterface::class, $repository->cache);
    }

    /**
     * Symfony's login_throttling on the visitors' firewall (five tries a minute, the sites' setting): the sixth wrong
     * attempt is refused and told to wait one minute - not two (the sites' final pass saw "dans 2 minutes").
     */
    public function testTheSixthWrongAttemptWaitsOneMinute(): void
    {
        $kernel = $this->boot([], 'throttling');
        $limits = $kernel->getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(\Psr\Cache\CacheItemPoolInterface::class, $limits);
        $limits->clear();
        $answers = [];
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $answers[] = $kernel->handle(Request::create('/sign-in?user=nobody'))->getContent();
        }

        self::assertSame(array_fill(0, 5, 'refused: nobody'), \array_slice($answers, 0, 5), 'five wrong attempts');
        self::assertSame('refused: nobody (throttled, 1 min)', $answers[5]);
    }

    public function testAVisitorMeetsTheEntryPointOfEachFirewall(): void
    {
        $kernel = $this->boot(['security' => ['access_control' => [
            ['path' => '^/rescue', 'roles' => 'ROLE_ADMIN'],
            ['path' => '^/private', 'roles' => 'ROLE_USER'],
        ]]], 'access');

        self::assertSame('home', $kernel->handle(Request::create('/'))->getContent());
        $private = $kernel->handle(Request::create('/private'));
        self::assertSame(401, $private->getStatusCode());
        self::assertSame('entry point: sign-in', $private->getContent());
        self::assertSame('entry point: rescue', $kernel->handle(Request::create('/rescue/private'))->getContent());
    }

    public function testDoctrineIsGivenTheNamingStrategyAndTheMetadataFactory(): void
    {
        $doctrine = self::ids($this->boot()->getContainer(), 'host_test.doctrine');

        self::assertSame([HostConfiguration::NAMING_STRATEGY], $doctrine['setNamingStrategy']);
        self::assertSame([HostConfiguration::METADATA_FACTORY], $doctrine['setClassMetadataFactoryName']);
        self::assertSame([HostConfiguration::REPOSITORY_FACTORY], $doctrine['setRepositoryFactory']);
    }

    public function testAnErrorIsAnsweredByOmnibasesErrorController(): void
    {
        $kernel = $this->boot();

        self::assertSame(HostConfiguration::ERROR_CONTROLLER, $kernel->getContainer()->getParameter('kernel.error_controller'));
        $missing = $kernel->handle(Request::create('/no-such-page'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertStringStartsWith('error controller: ', (string) $missing->getContent());
    }

    /**
     * A site's entities are written in omnibase's vocabulary (Base\Mapping, E3), its doctrine.yaml says only where
     * they are: Doctrine maps them through omnibridge/doctrine's DeclarationDriver, which the bridge puts around
     * Doctrine's driver (DoctrineConfigurationPass). Needs omnibridge/doctrine and the core (Base\Mapping).
     */
    public function testAnEntityWrittenInOmnibasesVocabularyIsMappedByDoctrine(): void
    {
        if (!class_exists(\Base\Mapping\Entity::class) || !class_exists(\Omnibridge\Doctrine\DeclarationDriver::class)) {
            self::markTestSkipped('Needs omnisense/omnibase (Base\Mapping) and omnibridge/doctrine (DeclarationDriver).');
        }
        $container = $this->boot([], 'mapping')->getContainer()->get('test.service_container');
        \assert($container instanceof \Symfony\Component\DependencyInjection\ContainerInterface);
        $entityManager = $container->get('doctrine.orm.entity_manager');
        \assert($entityManager instanceof \Doctrine\ORM\EntityManagerInterface);

        self::assertContains(\App\Entity\Note::class, $entityManager->getConfiguration()->getMetadataDriverImpl()?->getAllClassNames() ?? [], 'listed among the mapped classes');
        $metadata = $entityManager->getClassMetadata(\App\Entity\Note::class);
        self::assertSame('host_note', $metadata->getTableName());
        self::assertSame(['id', 'title'], $metadata->getFieldNames());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
    }

    public function testTheOptionsNameOtherFirewallsOrNone(): void
    {
        $container = $this->boot(['security' => ['firewall' => 'rescue', 'rescue' => null, 'access_token' => false]], 'renamed')->getContainer();

        $rescue = self::ids($container, 'host_test.firewall.rescue');
        self::assertContains(HostConfiguration::SIGN_IN, $rescue);
        self::assertNotContains('access_token', $rescue);
        self::assertNotContains(HostConfiguration::USER_CHECKER, self::ids($container, 'host_test.firewall.main'));
    }

    public function testTheSiteKeepsTheLastWord(): void
    {
        $options = ['firewall' => 'main', 'sign_in' => HostConfiguration::OMNIAUTH] + HostConfiguration::DEFAULTS;
        $builder = new \Symfony\Component\DependencyInjection\ContainerBuilder();
        $builder->prependExtensionConfig('security', ['firewalls' => ['main' => ['pattern' => '^/', 'oauth' => ['resource_owners' => ['google' => '/login/google']]], 'api' => ['pattern' => '^/api']]]);

        $security = HostConfiguration::security($builder, $options, false);
        $firewalls = $security['firewalls'] ?? [];
        self::assertSame(['main', 'api'], array_keys($firewalls), 'every firewall of the site, in its order');
        self::assertSame([], $firewalls['api']);
        self::assertSame([HostConfiguration::OMNIAUTH_AUTHENTICATOR], $firewalls['main']['custom_authenticators']);
        self::assertSame(['oauth_user_provider' => ['service' => HostConfiguration::OAUTH_USERS]], $firewalls['main']['oauth']);
        self::assertSame(['users' => ['id' => HostConfiguration::USER_PROVIDER]], $security['providers'] ?? [], 'omnibase\'s provider, on every persistence');
        self::assertSame(['users' => ['id' => HostConfiguration::USER_PROVIDER]], HostConfiguration::security($builder, $options, true)['providers'] ?? [], 'on Doctrine too: not Doctrine\'s entity provider');

        $builder->prependExtensionConfig('doctrine', ['orm' => ['entity_managers' => ['default' => [], 'archive' => []]]]);
        self::assertSame(['default', 'archive'], array_keys(HostConfiguration::orm($builder)['entity_managers'] ?? []));
    }
}
