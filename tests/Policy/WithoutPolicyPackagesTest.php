<?php

namespace Omnibridge\Symfony\Tests\Policy;

use Omnibridge\Symfony\Policy\Block;
use Omnibridge\Symfony\Policy\RateLimiter;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * omnisense/contracts and symfony/rate-limiter are only suggested: without them (`docker compose run --rm bare`, the
 * suite of this group in an install without them), a kernel of OmnibridgeBundle compiles and Policy\'s files load
 * and declare nothing. With them (the suite), they declare their classes.
 */
#[Group('without-omniauth')]
final class WithoutPolicyPackagesTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testPolicyDeclaresItsClassesOnlyWithItsPackages(): void
    {
        $contracts = interface_exists(\Omnisense\Contracts\Policy\RateLimiterInterface::class);
        $symfony = class_exists(\Symfony\Component\RateLimiter\RateLimiterFactory::class);

        self::bootKernel();
        self::assertTrue(static::getContainer()->has('router'), 'the kernel compiled');

        self::assertSame($symfony, class_exists(Block::class));
        self::assertSame($contracts && $symfony, class_exists(RateLimiter::class));
    }
}
