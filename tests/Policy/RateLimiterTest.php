<?php

namespace Omnibridge\Symfony\Tests\Policy;

use Omnibridge\Symfony\Policy\RateLimiter;
use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsageLimitReached;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Policy\RateLimiter: omnisense/contracts' RateLimiterInterface over symfony/rate-limiter, both in this package's
 * require-dev (only suggested: tests/Policy/WithoutPolicyPackagesTest is the install without them).
 */
final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(RateLimiter::class)) {
            self::markTestSkipped('omnisense/contracts and symfony/rate-limiter are not installed');
        }
    }

    public function testTwoGuardsOverOneStorageCountTogether(): void
    {
        $storage = new InMemoryStorage();
        $policy = UsagePolicy::builder('transitous')->limit(RateLimit::perDay(3, 'journeys'))->build();
        // Two processes of one application: two limiters, one storage.
        $first = new PolicyGuard($policy, new RateLimiter($storage, new LockFactory(new InMemoryStore())));
        $second = new PolicyGuard($policy, new RateLimiter($storage));

        $first->call('journeys', static fn () => null);
        $second->call('journeys', static fn () => null);
        self::assertSame(1, $first->remaining('journeys'));
        $first->call('journeys', static fn () => null);
        self::assertSame(0, $second->remaining('journeys'));
        try {
            $second->call('journeys', static fn () => null);
            self::fail('The fourth journey went through.');
        } catch (UsageLimitReached $e) {
            self::assertGreaterThan(86000, $e->retryAfter);
            self::assertLessThanOrEqual(86401, $e->retryAfter, 'symfony/rate-limiter rounds waits up to the second');
        }
        self::assertNull($first->remaining('departures'));
    }

    public function testAWaitIsKeptInTheStorage(): void
    {
        $storage = new InMemoryStorage();
        (new RateLimiter($storage))->block('backoff/transitous', 120);

        $limiter = new RateLimiter($storage);
        self::assertGreaterThanOrEqual(119, $limiter->blocked('backoff/transitous'));
        self::assertSame(0, $limiter->blocked('backoff/other'));
        $limiter->block('backoff/transitous', 0);
        self::assertSame(0, $limiter->blocked('backoff/transitous'));
    }

    public function testALimitOfZeroLetsNothingThrough(): void
    {
        $guard = new PolicyGuard(UsagePolicy::builder('x')->limit(RateLimit::perMinute(0))->build(), new RateLimiter(new InMemoryStorage()));

        $this->expectException(UsageLimitReached::class);
        $guard->check('any');
    }

    public function testAnApplicationsCachePoolCountsAcrossLimiters(): void
    {
        // A Symfony application's case: a CacheStorage over its pool (cache.rate_limiter).
        $pool = new ArrayAdapter();
        $policy = UsagePolicy::builder('transitous')->limit(RateLimit::perDay(2, 'journeys'))->build();
        (new PolicyGuard($policy, new RateLimiter(new CacheStorage($pool))))->call('journeys', static fn () => null);

        $guard = new PolicyGuard($policy, new RateLimiter(new CacheStorage($pool)));
        self::assertSame(1, $guard->remaining('journeys'));
        $guard->call('journeys', static fn () => null);

        $calls = 0;
        try {
            $guard->call('journeys', static function () use (&$calls): void {
                ++$calls;
            });
            self::fail('The third journey went through.');
        } catch (UsageLimitReached $e) {
            self::assertSame(0, $calls, 'the call is never made');
            self::assertSame(2, $e->limit?->max);
        }
    }
}
