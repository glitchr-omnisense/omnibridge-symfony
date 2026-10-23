<?php

namespace Omnibridge\Symfony\Tests;

use Omniauth\Factor;
use Omniauth\Link\LinkMethod;
use Omniauth\OAuth\Testing\FakeProvider;
use Omniauth\Otp\SealedTotpSecrets;
use Omniauth\Otp\SodiumSecretBox;
use Omniauth\Testing\RecordingSender;
use Omniauth\WebAuthn\PasskeyRecord;
use Omniauth\WebAuthn\Testing\InMemoryPasskeys;
use Omniauth\WebAuthn\Testing\SoftwareAuthenticator;
use Omnibridge\Symfony\Auth\OmniauthAuthenticator;
use Omnibridge\Symfony\Auth\PendingToken;
use Omnibridge\Symfony\Tests\Fixtures\Auth\TotpSecrets;
use Omnibridge\Symfony\Tests\Fixtures\Auth\User;
use Omnibridge\Symfony\Tests\Fixtures\Auth\Users;
use Omnibridge\Symfony\Tests\Fixtures\AuthKernel;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * omniauth under Symfony through Omnibridge\Symfony\Auth\ (plan "omniauth",
 * step AU6), in a kernel of FrameworkBundle, SecurityBundle, TwigBundle and
 * OmnibridgeBundle: each way of signing in signs in, the second factor is
 * asked when the account has one (and IS_AUTHENTICATED_2FA_IN_PROGRESS opens
 * its page and that page alone), refusals are omniauth's, the forms' CSRF
 * token is checked, remember-me and the trusted device follow.
 */
final class AuthTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return AuthKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '127.0.0.1']);
        $this->client->disableReboot();
        // The tries counted afresh: not those of the previous test, nor of a run a minute ago.
        $pool = static::getContainer()->get('omniauth.test_pool');
        \assert($pool instanceof \Psr\Cache\CacheItemPoolInterface);
        $pool->clear();
    }

    private function users(): Users
    {
        return static::getContainer()->get(Users::class);
    }

    private function account(?string $totp = null, bool $emailCode = false, array $backupCodes = []): User
    {
        $user = new User(bin2hex(random_bytes(4)), 'ada'.bin2hex(random_bytes(3)).'@example.org', password_hash('secret-password', \PASSWORD_BCRYPT, ['cost' => 13]), $totp);
        $user->emailCodeEnabled = $emailCode;
        $user->backupCodes = array_map(static fn (string $c) => password_hash($c, \PASSWORD_DEFAULT), $backupCodes);

        return $this->users()->add($user);
    }

    private function csrf(): string
    {
        $this->client->request('GET', '/login');

        return json_decode((string) $this->client->getResponse()->getContent(), true)['csrf'];
    }

    private function signIn(string $email, string $password = 'secret-password', bool $remember = false, ?string $csrf = null): Response
    {
        $csrf ??= $this->csrf();
        $this->client->request('POST', '/login', ['_username' => $email, '_password' => $password, '_csrf_token' => $csrf] + ($remember ? ['_remember_me' => '1'] : []));

        return $this->client->getResponse();
    }

    /** @return array{0: string, 1: string} the second factor's page, and its CSRF token */
    private function secondFactorPage(?string $prefer = null): array
    {
        $this->client->request('GET', '/login/2fa'.(null !== $prefer ? '?preferProvider='.$prefer : ''));
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'the second factor\'s page opens');
        $html = (string) $this->client->getResponse()->getContent();
        preg_match('~name="_csrf_token" value="([^"]+)"~', $html, $token);

        return [$html, $token[1] ?? ''];
    }

    private function secondFactor(string $code, bool $trust = false, ?string $prefer = null): Response
    {
        [, $token] = $this->secondFactorPage($prefer);
        $this->client->request('POST', '/login/2fa/check', ['_auth_code' => $code, '_csrf_token' => $token] + ($trust ? ['_trusted' => '1'] : []));

        return $this->client->getResponse();
    }

    /** @return 'signed-in'|'second-factor'|'signed-out'|string */
    private function state(): string
    {
        $this->client->request('GET', '/member');
        $response = $this->client->getResponse();
        if (200 === $response->getStatusCode()) {
            return 'signed-in';
        }
        $location = (string) $response->headers->get('Location');

        return match (true) {
            str_ends_with($location, '/login/2fa') => 'second-factor',
            str_ends_with($location, '/login') => 'signed-out',
            default => $response->getStatusCode().' '.$location,
        };
    }

    private function token(): ?object
    {
        return static::getContainer()->get('security.token_storage')->getToken();
    }

    private function lastError(): ?string
    {
        $this->client->request('GET', '/login');

        return json_decode((string) $this->client->getResponse()->getContent(), true)['error'];
    }

    public function testAPasswordSignsIn(): void
    {
        $user = $this->account();

        $response = $this->signIn($user->email);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
        self::assertSame('signed-in', $this->state());
        self::assertInstanceOf(PostAuthenticationToken::class, $this->token());
        self::assertSame($user->email, $this->token()->getUserIdentifier());
        self::assertNull($this->client->getCookieJar()->get('REMEMBERME'), 'no remember-me unless asked');
    }

    public function testAWrongPasswordAnUnknownAddressAndAForgedTokenAreRefused(): void
    {
        $user = $this->account();

        $response = $this->signIn($user->email, 'not-the-password');
        self::assertSame('/login', $response->headers->get('Location'));
        self::assertSame('Invalid credentials.', $this->lastError());
        self::assertSame('signed-out', $this->state());

        $this->signIn('nobody@example.org');
        self::assertSame('Invalid credentials.', $this->lastError(), 'the same answer for an address nobody has');

        $this->signIn($user->email, csrf: 'forged');
        self::assertSame('Invalid CSRF token.', $this->lastError());
        self::assertSame('signed-out', $this->state(), 'the right password without the form\'s token signs nobody in');
    }

    public function testTriesAreThrottled(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; ++$i) {
            $this->signIn($user->email, 'wrong');
        }

        $this->signIn($user->email);

        self::assertSame('Too many failed login attempts, please try again in %minutes% minute.', $this->lastError());
        self::assertSame('signed-out', $this->state(), 'even the right password, once the limit is reached');
    }

    public function testRememberMeIsSecuritysOwn(): void
    {
        $user = $this->account();

        $this->signIn($user->email, remember: true);

        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));
    }

    /**
     * totp.secret_key: the store decorated by omniauth/otp's SealedTotpSecrets
     * (whoever writes it - an enrolment page - seals), the account's secret
     * kept sealed, the sign-in verified against the opened secret; a secret
     * kept in clear before (the other tests' accounts) still signs in.
     */
    public function testTotpSecretsAreSealedAtRestWhenAKeyIsSet(): void
    {
        $store = static::getContainer()->get(TotpSecrets::class);
        self::assertInstanceOf(SealedTotpSecrets::class, $store, 'the store decorated');
        $user = $this->account();
        $secret = TOTP::generate()->getSecret();
        $store->enable($user, $secret);

        self::assertTrue(SodiumSecretBox::isSealed((string) $user->totpSecret), 'kept sealed');
        self::assertSame($secret, SodiumSecretBox::fromBase64(AuthKernel::TOTP_KEY)->open((string) $user->totpSecret), 'under the configured key');
        self::assertSame($secret, $store->secret($user));

        self::assertSame('/login/2fa', $this->signIn($user->email)->headers->get('Location'));
        $this->secondFactor(TOTP::createFromSecret($secret)->now());
        self::assertSame('signed-in', $this->state());
    }

    public function testAnAccountWithTotpIsAskedItsCodeAndOnlyItsPageOpens(): void
    {
        $secret = TOTP::generate()->getSecret();
        $user = $this->account(totp: $secret);

        $response = $this->signIn($user->email, remember: true);

        self::assertSame('/login/2fa', $response->headers->get('Location'));
        self::assertInstanceOf(PendingToken::class, $this->token());
        self::assertSame([], $this->token()->getRoleNames(), 'no role between two factors');
        self::assertSame('second-factor', $this->state(), 'a member\'s page sends to the second factor');
        $this->client->request('GET', '/');
        self::assertSame('/login/2fa', $this->client->getResponse()->headers->get('Location'), 'and so does any page access_control does not open');
        $this->client->request('GET', '/login');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), 'not one open to all');

        [$page] = $this->secondFactorPage();
        self::assertStringContainsString('<p id="provider">totp</p>', $page);

        $code = TOTP::createFromSecret($secret)->now();
        $this->secondFactor('000000' === $code ? '111111' : '000000');
        self::assertSame('second-factor', $this->state(), 'a wrong code keeps the visitor at the second factor');
        [$page] = $this->secondFactorPage();
        self::assertStringContainsString('<p id="error">code_invalid</p>', $page);

        $response = $this->secondFactor($code);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('signed-in', $this->state());
        self::assertInstanceOf(PostAuthenticationToken::class, $this->token());
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'), 'remember-me asked at the first factor, set at the last');
        self::assertNull($this->client->getCookieJar()->get('trusted_device'), 'no trusted device unless asked');

        $this->client->request('GET', '/login/2fa');
        self::assertSame(403, $this->client->getResponse()->getStatusCode(), 'IS_AUTHENTICATED_2FA_IN_PROGRESS is nobody\'s once signed in');
    }

    public function testTheSecondFactorsPageIsNoOnesWithoutAFirstFactor(): void
    {
        $this->client->request('GET', '/login/2fa');

        self::assertSame('/login', $this->client->getResponse()->headers->get('Location'));
    }

    public function testAnEmailedCodeIsSentHashedAndServesOnce(): void
    {
        $user = $this->account(emailCode: true);
        $sender = static::getContainer()->get(RecordingSender::class);

        $this->signIn($user->email);
        self::assertSame('second-factor', $this->state());
        [$page] = $this->secondFactorPage();
        self::assertStringContainsString('<p id="provider">email</p>', $page, 'scheb/2fa\'s name');

        $message = $sender->last();
        self::assertSame(Factor::EmailCode, $message?->factor);
        self::assertMatchesRegularExpression('~^\d{6}$~', $message->secret);
        self::assertMatchesRegularExpression('~^[0-9a-f]{64}$~', (string) $user->emailCode, 'kept hashed');
        $this->secondFactorPage();
        self::assertSame(1, $sender->count(), 'not sent again while it lives');

        $this->secondFactor($message->secret);
        self::assertSame('signed-in', $this->state());
        self::assertNull($user->emailCode, 'used up');
    }

    public function testABackupCodeStandsForTheTotpOnce(): void
    {
        $user = $this->account(totp: TOTP::generate()->getSecret(), backupCodes: ['A1B2C3-D4E5F6', '11112222']);

        $this->signIn($user->email);
        [$page] = $this->secondFactorPage();
        self::assertStringContainsString('<p id="providers">totp</p>', $page, 'backup codes are no provider of their own');
        $this->secondFactor('11112222');
        self::assertSame('signed-in', $this->state());
        self::assertCount(1, $user->backupCodes);

        $this->client->getCookieJar()->clear();
        $this->signIn($user->email);
        $this->secondFactor('11112222');
        self::assertSame('second-factor', $this->state(), 'not twice');
    }

    public function testATrustedDeviceSkipsTheSecondFactorUntilRevoked(): void
    {
        $secret = TOTP::generate()->getSecret();
        $user = $this->account(totp: $secret);

        $this->signIn($user->email);
        $this->secondFactor(TOTP::createFromSecret($secret)->now(), trust: true);
        self::assertSame('signed-in', $this->state());
        $cookie = $this->client->getCookieJar()->get('trusted_device');
        self::assertNotNull($cookie);
        $claims = json_decode((string) base64_decode(strtr(explode('.', $cookie->getValue())[1], '-_', '+/')), true);
        self::assertSame([$user->email, 'main', 0], [$claims['usr'], $claims['fwl'], $claims['vsn']], 'scheb/2fa\'s claims');

        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($cookie);
        $this->signIn($user->email);
        self::assertSame('signed-in', $this->state(), 'no second factor on a trusted device');

        ++$user->trustedVersion;
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($cookie);
        $this->signIn($user->email);
        self::assertSame('second-factor', $this->state(), 'its devices revoked: asked again');
    }

    public function testASignInLinkServesOnceAndAFailedOneIsNoLink(): void
    {
        $user = $this->account();
        $sender = static::getContainer()->get(RecordingSender::class);

        $this->client->request('POST', '/link', ['email' => $user->email]);
        self::assertSame('sent', $this->client->getResponse()->getContent());
        $token = $sender->last()?->secret;
        self::assertNotNull($token);
        self::assertSame([LinkMethod::hash($token)], array_keys($user->links), 'the store holds its hash');

        $this->client->request('GET', '/link/'.$token);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('signed-in', $this->state());
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'), 'a link from a mailbox remembers the browser');

        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/link/'.$token);
        self::assertSame('link page, token NULL', $this->client->getResponse()->getContent(), 'used once: the page answers as for no link');
        self::assertSame('signed-out', $this->state());
    }

    public function testAPasskeySignsInWithoutTheSecondFactor(): void
    {
        $user = $this->account(totp: TOTP::generate()->getSecret());
        $authenticator = (new SoftwareAuthenticator('http://localhost'))->belongsTo($user->handle);
        static::getContainer()->get(InMemoryPasskeys::class)->add($user, new PasskeyRecord(base64_encode($authenticator->credentialId), credentialPublicKey: base64_encode($authenticator->publicKey()), userHandle: $user->handle));

        $this->client->request('POST', '/login/passkey/options', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $options = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['ok', ''], [$options['status'], $options['errorMessage']], 'the bundle\'s JSON');
        self::assertSame('localhost', $options['rpId']);

        $this->client->request('POST', '/login/passkey', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($authenticator->get($options)));
        self::assertSame(['status' => 'ok', 'errorMessage' => ''], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertSame('signed-in', $this->state(), 'QA1: no second factor after a passkey');
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'), 'remembered');

        $this->client->getCookieJar()->clear();
        $this->client->request('POST', '/login/passkey/options', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $options = json_decode((string) $this->client->getResponse()->getContent(), true);
        $impostor = (new SoftwareAuthenticator('http://localhost', credentialId: $authenticator->credentialId))->belongsTo($user->handle);
        $this->client->request('POST', '/login/passkey', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($impostor->get($options)));
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertSame('failed', json_decode((string) $this->client->getResponse()->getContent(), true)['status']);
        self::assertSame('signed-out', $this->state(), 'another key\'s signature signs nobody in');
    }

    /** @return array{code: string, state: string} */
    private function toGoogle(FakeProvider $google, array $person): array
    {
        $this->client->request('GET', '/connect/google');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(FakeProvider::AUTHORIZE, $location);

        return $google->consent($location, $person);
    }

    public function testAnIdentityProviderSignsInByItsSubjectAndAsksTheSecondFactor(): void
    {
        $google = static::getContainer()->get('test.google');
        $user = $this->account();

        // A verified address links an account without a second factor.
        $this->client->request('GET', '/connect/google/check', $this->toGoogle($google, ['sub' => 'g-1', 'email' => $user->email, 'email_verified' => true]));
        self::assertSame('signed-in', $this->state());
        self::assertSame(['google' => 'g-1'], $user->identities);

        // QA2: a linked account with a TOTP is asked it.
        $secret = TOTP::generate()->getSecret();
        $other = $this->account(totp: $secret);
        $other->identities['google'] = 'g-2';
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/connect/google/check', $this->toGoogle($google, ['sub' => 'g-2', 'email' => 'elsewhere@example.org']));
        self::assertSame('second-factor', $this->state(), 'by its subject, whatever the address, then the second factor');
        $this->secondFactor(TOTP::createFromSecret($secret)->now());
        self::assertSame('signed-in', $this->state());

        // Nobody: no account made, the identity kept for a sign-up.
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/connect/google/check', $this->toGoogle($google, ['sub' => 'g-3', 'email' => 'new@example.org', 'email_verified' => true]));
        self::assertSame('/login', $this->client->getResponse()->headers->get('Location'));
        self::assertSame('g-3', $this->client->getRequest()->getSession()->get(OmniauthAuthenticator::PENDING_IDENTITY)['subject']);
        self::assertNull($this->users()->byIdentifier('new@example.org'));
        self::assertSame('signed-out', $this->state());

        // A forged state: refused before any exchange.
        $this->client->getCookieJar()->clear();
        $return = $this->toGoogle($google, ['sub' => 'g-1']);
        $exchanges = \count($google->exchanges);
        $this->client->request('GET', '/connect/google/check', ['state' => 'forged'] + $return);
        self::assertSame('signed-out', $this->state());
        self::assertCount($exchanges, $google->exchanges, 'no code exchanged');
    }

    /**
     * Under a long-running worker the providers are built once: the return
     * address must still be the host of each request (AU6 found it frozen on
     * the first one), at the trip out and at the exchange.
     */
    public function testTheReturnAddressFollowsTheHostOfEachRequest(): void
    {
        $this->client->disableReboot();   // one container for every request, as a worker keeps it
        $google = static::getContainer()->get('test.google');
        \assert($google instanceof FakeProvider);
        $user = $this->account();
        foreach (['lesringostars.localhost', 'twin.localhost'] as $host) {
            $this->client->getCookieJar()->clear();
            $this->client->setServerParameters(['HTTP_HOST' => $host, 'HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.1']);
            $this->client->request('GET', '/connect/workspace');
            parse_str((string) parse_url((string) $this->client->getResponse()->headers->get('Location'), \PHP_URL_QUERY), $query);
            self::assertSame("https://$host/connect/workspace/check", $query['redirect_uri'] ?? $this->client->getResponse()->headers->get('Location'), 'Google by configuration');

            $this->client->request('GET', '/connect/google');
            $location = (string) $this->client->getResponse()->headers->get('Location');
            parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
            self::assertSame("https://$host/connect/google/check", $query['redirect_uri'] ?? $location);
            $this->client->request('GET', '/connect/google/check', $google->consent($location, ['sub' => 'g-host', 'email' => $user->email, 'email_verified' => true]));
            self::assertSame('signed-in', $this->state(), $host.': the exchange repeated the trip\'s address');
        }
    }

    public function testAnUnservedProviderIsNoPage(): void
    {
        $this->client->request('GET', '/connect/nobody');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }
}
