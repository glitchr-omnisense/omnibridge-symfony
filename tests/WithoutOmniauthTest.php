<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Auth\CeremonyController;
use Omnibridge\Symfony\Auth\OmniauthAuthenticator;
use Omnibridge\Symfony\Auth\SecondFactorGate;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;
use Omniauth\Omniauth;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * omnisense/omniauth is only suggested (plan "omniauth", QA8): without it -
 * `docker compose run --rm bare`, the suite of this group in an install
 * without the omniauth packages -, or without omniauth.yaml, a kernel of
 * OmnibridgeBundle compiles and has nothing of Auth\: no authenticator, no
 * gate, no controller, no conductor.
 */
#[Group('without-omniauth')]
final class WithoutOmniauthTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testNothingOfAuthIsWired(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        foreach ([OmniauthAuthenticator::class, SecondFactorGate::class, CeremonyController::class, Omniauth::class] as $id) {
            self::assertFalse($container->has($id), $id);
        }
        self::assertTrue($container->has('router'), 'the kernel compiled');
    }
}
