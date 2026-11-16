<?php

namespace Omnibridge\Symfony\Omnibase;

use Symfony\Component\HttpFoundation\RateLimiter\PeekableRequestRateLimiterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * login_throttling's limiter of a firewall, its refusal's wait said in whole minutes as the window says it.
 *
 * symfony/rate-limiter 8.1 rounds the instant a window ends UP to the second (FixedWindowLimiter: "the window rounds
 * waits up to the second"), and security-http's LoginThrottlingListener says `ceil((retryAfter - time()) / 60)`
 * minutes: a refusal within the second the window opened - the sixth of six quick wrong passwords - was told
 * "2 minutes" for a one-minute window. The instant is given back one second earlier (what the rounding added at most),
 * the refusal itself untouched (accepted, the tokens left: the limiter's own).
 *
 * @internal plumbing of omnibridge/symfony (Base\DependencyInjection\Compiler\Pass\LoginThrottlingPass)
 */
final class LoginThrottle implements PeekableRequestRateLimiterInterface
{
    public function __construct(private readonly PeekableRequestRateLimiterInterface $limiter)
    {
    }

    public function consume(Request $request): RateLimit
    {
        return self::said($this->limiter->consume($request));
    }

    public function peek(Request $request): RateLimit
    {
        return self::said($this->limiter->peek($request));
    }

    public function reset(Request $request): void
    {
        $this->limiter->reset($request);
    }

    private static function said(RateLimit $limit): RateLimit
    {
        if ($limit->isAccepted() && $limit->getRemainingTokens() > 0) {
            return $limit;
        }
        $retryAfter = $limit->getRetryAfter();
        if ($retryAfter->getTimestamp() - time() <= 0) {
            return $limit;
        }

        return new RateLimit($limit->getRemainingTokens(), $retryAfter->modify('-1 second'), $limit->isAccepted(), $limit->getLimit());
    }
}
