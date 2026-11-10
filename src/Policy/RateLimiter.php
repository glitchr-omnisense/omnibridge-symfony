<?php

namespace Omnibridge\Symfony\Policy;

use Omnisense\Contracts\Policy\Allowance;
use Omnisense\Contracts\Policy\ConsumingRateLimiterInterface;
use Omnisense\Contracts\Policy\RateLimit;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

// omnisense/contracts and symfony/rate-limiter are only suggested: unless both are installed, this file declares nothing
// (class_exists(RateLimiter::class) is false), so a host can test for it before wiring it. ConsumingRateLimiterInterface
// came with omnisense/contracts 5bac28b: an older checkout declares nothing here either.
if (!interface_exists(ConsumingRateLimiterInterface::class) || !class_exists(RateLimiterFactory::class)) {
    return;
}

/**
 * omnisense/contracts' rate limiter over symfony/rate-limiter: each limit of
 * a usage policy is a fixed_window RateLimiterFactory of its own (its
 * maximum, its window in seconds: every times the window, 300 for 500 every
 * 5 minutes), over the application's storage (a
 * CacheStorage on a shared pool: every process counts together) and its
 * LockFactory when one is given (no two processes count the same window at
 * once). A provider's waits (Retry-After) are kept in the same storage, as
 * a Block state. consume() counts a call's amount at once (3 quota units,
 * 1,200 characters): one round trip to the storage, not one per unit.
 *
 *   $limiter = new RateLimiter(new CacheStorage($cachePool), $lockFactory);
 *   $registry = new Omnitravel\Registry($factories, $config, $limiter);
 *
 * Requires omnisense/contracts and symfony/rate-limiter (both suggested);
 * symfony/lock is optional. omnisense/contracts' own InMemoryRateLimiter
 * counts in one process only.
 */
final class RateLimiter implements ConsumingRateLimiterInterface
{
    /** @var array<string, RateLimiterFactory> */
    private array $factories = [];

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly ?LockFactory $lockFactory = null,
        private readonly string $prefix = 'omnisense',
    ) {
    }

    public function allowance(string $key, RateLimit $limit): Allowance
    {
        if (0 === $limit->max) {
            return new Allowance(0, $limit->seconds());
        }
        // Zero tokens: a look at the window, nothing counted.
        $rateLimit = $this->factory($limit)->create($key)->consume(0);

        return new Allowance($rateLimit->getRemainingTokens(), max(0, $rateLimit->getRetryAfter()->getTimestamp() - time()));
    }

    public function hit(string $key, RateLimit $limit): void
    {
        $this->consume($key, $limit, 1);
    }

    public function consume(string $key, RateLimit $limit, int $amount): void
    {
        if ($limit->max <= 0 || $amount <= 0) {
            return;
        }
        $limiter = $this->factory($limit)->create($key);
        // symfony/rate-limiter refuses more than the window holds, and counts nothing of an amount over what is left: an
        // amount only known after the call (the characters billed) still spent the window, so what is left is taken.
        $rateLimit = $limiter->consume(min($amount, $limit->max));
        if (!$rateLimit->isAccepted() && $rateLimit->getRemainingTokens() > 0) {
            $limiter->consume($rateLimit->getRemainingTokens());
        }
    }

    public function block(string $key, int $seconds): void
    {
        $id = $this->prefix.'/block/'.$key;
        if ($seconds <= 0) {
            $this->storage->delete($id);

            return;
        }
        $current = $this->storage->fetch($id);
        $until = microtime(true) + $seconds;
        $this->storage->save(new Block($id, $current instanceof Block ? max($current->until, $until) : $until));
    }

    public function blocked(string $key): int
    {
        $block = $this->storage->fetch($this->prefix.'/block/'.$key);
        $left = $block instanceof Block ? $block->until - microtime(true) : 0.0;

        return $left > 0 ? (int) ceil($left) : 0;
    }

    private function factory(RateLimit $limit): RateLimiterFactory
    {
        return $this->factories[$limit->id()] ??= new RateLimiterFactory([
            'id' => $this->prefix.'/'.$limit->id(),
            'policy' => 'fixed_window',
            'limit' => $limit->max,
            'interval' => $limit->seconds().' seconds',
        ], $this->storage, $this->lockFactory);
    }
}
