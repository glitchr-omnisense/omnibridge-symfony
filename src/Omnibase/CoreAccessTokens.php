<?php

namespace Omnibridge\Symfony\Omnibase;

use Base\Repository\User\Tokens;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;

/**
 * The Bearer tokens of the visitors' firewall that omnibase issued (its Base\Entity\User\Token, a sign-in token), and
 * only those: another one - a package's own (omnibase/forge's hub tokens and licence keys), an API's - is no token of
 * this authenticator: the request goes on unauthenticated by it, for whoever reads that token. omnibase's tokens have
 * no prefix: a token is the core's when its repository knows it (a token it knows but refuses - revoked, expired - is
 * still refused by Base\Security\AccessTokenHandler).
 *
 * @internal plumbing of omnibridge/symfony, the visitors' firewall's token extractor (HostConfiguration)
 */
class CoreAccessTokens implements AccessTokenExtractorInterface
{
    private readonly HeaderAccessTokenExtractor $header;

    public function __construct(private readonly ?Tokens $tokens = null)
    {
        $this->header = new HeaderAccessTokenExtractor();
    }

    public function extractAccessToken(Request $request): ?string
    {
        $token = $this->header->extractAccessToken($request);

        return null !== $token && '' !== $token && $this->issued($token) ? $token : null;
    }

    /** Whether omnibase issued this token. */
    protected function issued(string $token): bool
    {
        return null !== $this->tokens?->oneSigningInByValue($token);
    }
}
