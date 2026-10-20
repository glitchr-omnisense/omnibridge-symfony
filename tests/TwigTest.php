<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Runtime;
use Omnibridge\Symfony\Contract\CsrfTokens;
use Omnibridge\Symfony\Contract\SecurityAccess;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;
use Omnibridge\Twig\AccessExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Twig\Environment;

/**
 * A page's is_granted() and csrf_token() under Symfony are omnibridge/twig's
 * AccessExtension on the contracts, the code a page asks under every host:
 * registered after twig-bridge's own, answered by the same services (the
 * authorization checker, the CSRF manager).
 */
final class TwigTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Runtime::reset();
    }

    private static function twig(): Environment
    {
        return static::getContainer()->get('twig');
    }

    public function testThePagesFunctionsAreTheContracts(): void
    {
        self::bootKernel();
        $twig = self::twig();

        self::assertTrue($twig->hasExtension(AccessExtension::class));
        foreach (['is_granted' => SecurityAccess::class, 'csrf_token' => CsrfTokens::class] as $name => $adapter) {
            $callable = $twig->getFunction($name)?->getCallable();
            self::assertInstanceOf(\Closure::class, $callable, $name);
            self::assertInstanceOf($adapter, (new \ReflectionFunction($callable))->getClosureThis(), $name.': the contract\'s adapter, not twig-bridge\'s runtime');
        }
    }

    public function testTheyAnswerAsTheFrameworksServices(): void
    {
        self::bootKernel();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $page = self::twig()->createTemplate('{{ is_granted("ROLE_USER") ? "user" : "visitor" }}|{{ csrf_token("subscriber") }}');

        [$granted, $token] = explode('|', $page->render());
        self::assertSame('visitor', $granted);
        self::assertTrue(static::getContainer()->get('security.csrf.token_manager')->isTokenValid(new CsrfToken('subscriber', $token)), 'the CSRF manager\'s token of that intent');

        $ada = new InMemoryUser('ada', 'x', ['ROLE_ADMIN']);
        static::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($ada, 'main', $ada->getRoles()));
        self::assertStringStartsWith('user|', $page->render(), 'the role hierarchy');
    }

    public function testWithoutSecurityTheContractsLeaveTheFunctionsOut(): void
    {
        $kernel = new TestKernel('test', true, security: false);
        $kernel->boot();
        try {
            $twig = $kernel->getContainer()->get('test.service_container')->get('twig');
            self::assertTrue($twig->hasExtension(AccessExtension::class));
            self::assertSame([], $twig->getExtension(AccessExtension::class)->getFunctions(), 'no Access, no Tokens: no function of its own');
        } finally {
            $kernel->shutdown();
        }
    }
}
