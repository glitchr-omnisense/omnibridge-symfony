<?php

namespace Omnibridge\Symfony\Tests;

use Base\Repository\User\Tokens;
use Omnibridge\Symfony\Omnibase\CoreAccessTokens;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/** The visitors' firewall's extractor: a Bearer token is claimed when omnibase's token repository knows it, only then. */
final class CoreAccessTokensTest extends TestCase
{
    public function testATokenTheRepositoryKnowsIsClaimedAnotherIsNot(): void
    {
        if (!interface_exists(Tokens::class)) {
            self::markTestSkipped('omnisense/omnibase is not installed.');
        }
        \Base\Service\Model\ClassAliases::register();
        $tokens = $this->createStub(Tokens::class);
        $tokens->method('oneSigningInByValue')->willReturnCallback(fn (?string $value): ?object => 'kQ2v8rT0b1n' === $value ? $this->createStub(\Base\Entity\User\Token::class) : null);
        $extractor = new CoreAccessTokens($tokens);
        $bearer = static fn (string $token): Request => Request::create('/', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertSame('kQ2v8rT0b1n', $extractor->extractAccessToken($bearer('kQ2v8rT0b1n')));
        self::assertNull($extractor->extractAccessToken($bearer('gla_0123456789abcdef')), 'omnibase/forge\'s hub token');
        self::assertNull($extractor->extractAccessToken($bearer('glk_licence')), 'omnibase/forge\'s licence key');
        self::assertNull($extractor->extractAccessToken(Request::create('/')));
        self::assertNull((new CoreAccessTokens())->extractAccessToken($bearer('kQ2v8rT0b1n')), 'no repository: no token is omnibase\'s');
    }
}
