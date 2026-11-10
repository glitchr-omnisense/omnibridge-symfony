<?php

namespace Omnibridge\Symfony\Tests\Policy;

use Omnibridge\Symfony\Policy\RateLimiter;
use Omnisense\Contracts\Policy\ConsumingRateLimiterInterface;
use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\Unit;
use Omnisense\Contracts\Policy\UsageLimitReached;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

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

    public function testALimitOverSeveralWindowsCountsItsWholeLength(): void
    {
        // Trakt's 500 every 5 minutes, smaller: a window of 300 seconds, not 60.
        $limiter = new RateLimiter(new InMemoryStorage());
        $guard = new PolicyGuard(UsagePolicy::builder('trakt')->limit(RateLimit::perMinute(2)->every(5))->build(), $limiter);
        $guard->call('search', static fn () => null);
        $guard->call('search', static fn () => null);

        try {
            $guard->call('search', static fn () => null);
            self::fail('The third call went through.');
        } catch (UsageLimitReached $e) {
            self::assertGreaterThan(240, $e->retryAfter, 'the window is 5 minutes long');
            self::assertLessThanOrEqual(301, $e->retryAfter);
        }
        self::assertSame(300, $limiter->allowance('other', RateLimit::perMinute(0)->every(5))->resetsIn);
    }

    public function testAnAmountIsConsumedAtOnce(): void
    {
        // A storage that counts its writes (an anonymous class: this file loads in the bare install too).
        $storage = new class(new InMemoryStorage()) implements StorageInterface {
            public int $saves = 0;

            public function __construct(private readonly StorageInterface $storage)
            {
            }

            public function save(LimiterStateInterface $limiterState): void
            {
                ++$this->saves;
                $this->storage->save($limiterState);
            }

            public function fetch(string $limiterStateId): ?LimiterStateInterface
            {
                return $this->storage->fetch($limiterStateId);
            }

            public function delete(string $limiterStateId): void
            {
                $this->storage->delete($limiterStateId);
            }
        };
        $limiter = new RateLimiter($storage);
        self::assertInstanceOf(ConsumingRateLimiterInterface::class, $limiter);
        $policy = UsagePolicy::builder('deepl')->limit(RateLimit::perMonth(5000)->counting(Unit::Characters))->build();
        $guard = new PolicyGuard($policy, $limiter);

        $guard->call('translate', static fn () => null, amounts: ['characters' => 1200]);
        self::assertSame(1, $storage->saves, '1,200 characters, one round trip to the storage');
        self::assertSame(3800, $guard->remaining('translate'));

        $this->expectException(UsageLimitReached::class);
        $guard->check('translate', amounts: ['characters' => 3801]);
    }

    public function testACostPerOperationIsCounted(): void
    {
        $policy = UsagePolicy::builder('youtube')->limit(RateLimit::perDay(10)->counting(Unit::Units, ['channelVideos' => 3]))->build();
        $guard = new PolicyGuard($policy, new RateLimiter(new InMemoryStorage()));

        $guard->call('channelVideos', static fn () => null);
        $guard->call('video', static fn () => null);
        self::assertSame(2, $guard->remaining('channelVideos'), '6 units left, 3 a call');
        self::assertSame(6, $guard->remaining('video'));
    }

    public function testAnAmountOverWhatIsLeftSpendsTheWindow(): void
    {
        // The characters billed, known after the call, more than the window holds: the window is spent, nothing thrown.
        $limiter = new RateLimiter(new InMemoryStorage());
        $limit = RateLimit::perDay(10)->counting(Unit::Characters);
        $limiter->consume('deepl', $limit, 4);
        $limiter->consume('deepl', $limit, 9);
        self::assertSame(0, $limiter->allowance('deepl', $limit)->remaining);
        $limiter->consume('deepl', $limit, 25);
        self::assertSame(0, $limiter->allowance('deepl', $limit)->remaining);

        $limiter->consume('other', $limit, 0);
        self::assertSame(10, $limiter->allowance('other', $limit)->remaining);
    }
}
