<?php

namespace Omnibridge\Symfony\Tests\Omnibase;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A moved class's service answers under both of its names (FormerServiceIdsPass): the former id it is
 * registered by and the new class name a constructor is typed on - one service, the same instance.
 *
 * Needs the core and a host kernel: run in the omnibase harness, where omnibridge/symfony is the
 * checkout; skipped by this package's own suite, which installs no core.
 */
class FormerServiceIdsTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel') || !class_exists('Base\\Database\\FormerNames')) {
            self::markTestSkipped('Requires omnisense/omnibase and a host application kernel (the omnibase harness).');
        }

        self::bootKernel();
    }

    /** @return iterable<string, array{string, string}> */
    public static function services(): iterable
    {
        yield 'screen responder' => ['Base\\Bridge\\Symfony\\ScreenResponder', 'Omnibridge\\Symfony\\Omnibase\\ScreenResponder'];
        yield 'screen context' => ['Base\\Bridge\\Symfony\\SymfonyScreenContext', 'Omnibridge\\Symfony\\Omnibase\\SymfonyScreenContext'];
        yield 'router' => ['Base\\Routing\\AdvancedRouterInterface', 'Omnibridge\\Symfony\\Omnibase\\Routing\\AdvancedRouterInterface'];
        yield 'user provider' => ['Base\\Security\\UserProvider', 'Omnibridge\\Symfony\\Omnibase\\Security\\UserProvider'];
    }

    /** @dataProvider services */
    #[\PHPUnit\Framework\Attributes\DataProvider('services')]
    public function testBothNamesResolveToTheSameInstance(string $former, string $new): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->has($former), $former);
        $this->assertTrue($container->has($new), $new);
        $this->assertSame($container->get($former), $container->get($new));
    }

    public function testTheScreenResponderIsPublicUnderItsNewName(): void
    {
        $this->assertTrue(static::$kernel->getContainer()->has('Omnibridge\\Symfony\\Omnibase\\ScreenResponder'), 'public, as its former id');
    }

    public function testEveryMovedServiceTheContainerKeptHasItsNewName(): void
    {
        $container = static::getContainer();
        $missing = [];
        foreach (\Base\Database\FormerNames::moved() as $former => $new) {
            if ($container->has($former) && !$container->has($new)) {
                $missing[] = $former;
            }
        }

        $this->assertSame([], $missing);
    }
}
