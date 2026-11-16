<?php

namespace Omnibridge\Symfony\Tests;

use Base\Repository\User\Connections;
use Base\Security\UserProvider;
use Base\Security\UserTracker;
use Base\Service\SecurityPolicy;
use Base\Subscriber\NewDeviceSubscriber;
use Omnibridge\Symfony\Tests\Fixtures\Host\UserLoaderUsers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Where a browser's connection is recorded: when the account signs in (NewDeviceSubscriber on LoginSuccessEvent), never
 * when the session's account is reloaded on a later request (UserProvider::refreshUser() only reloads it).
 */
final class SignInConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(UserTracker::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        \Base\Service\Model\ClassAliases::register();
    }

    public function testARefreshOnlyReloadsTheAccount(): void
    {
        $user = $this->createStub(\App\Entity\User::class);
        $user->method('getId')->willReturn(7);
        $users = $this->createStub(UserLoaderUsers::class);
        $users->method('find')->willReturn($user);
        $tracker = $this->createMock(UserTracker::class);
        $tracker->expects(self::never())->method('updateConnection');

        self::assertSame($user, (new UserProvider($tracker, $users))->refreshUser($user));
    }

    public function testASignInRecordsTheConnection(): void
    {
        $user = $this->createStub(\App\Entity\User::class);
        $user->method('getId')->willReturn(7);
        $tracker = $this->createMock(UserTracker::class);
        $tracker->expects(self::once())->method('updateConnection')->with($user);
        $policy = $this->createStub(SecurityPolicy::class);
        $policy->method('isNewDeviceEmailEnabled')->willReturn(false);
        $policy->method('isNewDevicePromptEnabled')->willReturn(false);

        $passport = new SelfValidatingPassport(new UserBadge('ada', static fn () => $user));
        $event = new LoginSuccessEvent($this->createStub(AuthenticatorInterface::class), $passport, new UsernamePasswordToken($user, 'main', []), Request::create('/'), null, 'main');
        (new NewDeviceSubscriber($policy, $tracker, $this->createStub(Connections::class)))->onLoginSuccess($event);
    }
}
