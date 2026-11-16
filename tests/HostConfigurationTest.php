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

        $builder->prependExtensionConfig('doctrine', ['orm' => ['entity_managers' => ['default' => [], 'archive' => []]]]);
        self::assertSame(['default', 'archive'], array_keys(HostConfiguration::orm($builder)['entity_managers'] ?? []));
    }
}
