<?php

namespace Omnibridge\Symfony\Tests;

use Base\Contract\Runtime;
use Base\Entity\User;
use Base\Service\LocalizerInterface;
use Omnibridge\Contract\Locales;
use Omnibridge\Symfony\Omnibase\LocalizerLocales;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * The visitor's time zone under Symfony (Locales::getTimezone()): the localizer's, which LocalizerSubscriber sets from
 * the `timezone` cookie (UTC without one); a date without a time is compared with the visitor's day.
 */
final class VisitorTimezoneTest extends TestCase
{
    protected function tearDown(): void
    {
        Runtime::set(ClockInterface::class, null);
        Runtime::set(Locales::class, null);
    }

    public function testAVisitorInTokyoHasTokyosDayWhileTheApplicationIsInUtc(): void
    {
        if (!class_exists(User::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        // App\X -> Base\X, as BaseBundle registers it (App\Enum\UserRole...).
        \Base\Service\Model\ClassAliases::register();
        Runtime::set(ClockInterface::class, new class implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-03-01 16:00:00', new \DateTimeZone('UTC'));
            }
        });
        foreach (['Asia/Tokyo' => 26, 'UTC' => 25] as $zone => $age) {
            $localizer = $this->createStub(LocalizerInterface::class);
            $localizer->method('getTimezone')->willReturn($zone);
            $localizer->method('getLocale')->willReturn('fr-FR');
            $locales = new LocalizerLocales($localizer);
            Runtime::set(Locales::class, $locales);
            $user = (new User())->setBirthdate(new \DateTimeImmutable('2000-03-02', new \DateTimeZone('UTC')));

            self::assertSame($zone, $locales->getTimezone());
            self::assertSame($age, $user->getAge(), $zone);
        }
    }
}
