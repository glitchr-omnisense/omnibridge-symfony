<?php

namespace Omnibridge\Symfony\Policy;

use Symfony\Component\RateLimiter\LimiterStateInterface;

// symfony/rate-limiter is only suggested: without it, this file declares nothing (class_exists(Block::class) is false).
if (!interface_exists(LimiterStateInterface::class)) {
    return;
}

/**
 * A provider's wait in symfony/rate-limiter's storage: until when no call
 * goes out (RateLimiter::block()).
 */
final class Block implements LimiterStateInterface
{
    public function __construct(private readonly string $id, public readonly float $until)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getExpirationTime(): int
    {
        return max(1, (int) ceil($this->until - microtime(true)));
    }
}
