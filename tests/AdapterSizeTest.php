<?php

namespace Omnibridge\Symfony\Tests;

use PHPUnit\Framework\TestCase;

/**
 * omnisense/omnibase's three layers (its plan "omnibase agnostic", §2.5; step F5e): the host's classes of
 * Omnibase\{Security, Subscriber, Console\Command} are adapters - the host's event turned into a call of the
 * core's rule -, of an adapter's size (THRESHOLD lines of code, comments and blank lines left out), and decide
 * nothing on omnibase's entities themselves. The classes still above it, recorded here, are those F5e's next
 * slices empty or glue of the host's facts kept above it; the list only shrinks: a class leaves it when it is slimmed, none enters it.
 * The 4.x-only copies of the rules (Legacy\, removed once omnibase 4.x is no longer served) are not adapters.
 */
final class AdapterSizeTest extends TestCase
{
    public const THRESHOLD = 60;

    /** @var list<string> */
    public const STILL_ABOVE = [
        'Security/LoginFormAuthenticator.php',
        'Security/SignInGuard.php',
        'Security/UserChecker.php',
        'Security/UserProvider.php',
        'Subscriber/DemoBackOfficeSubscriber.php',
        'Subscriber/IntegritySubscriber.php',
        'Subscriber/IsGrantedSubscriber.php',
        'Subscriber/LocalizerSubscriber.php',
        'Subscriber/NewDeviceSubscriber.php',
        'Subscriber/ReferrerSubscriber.php',
        'Subscriber/RouterSubscriber.php',
        'Subscriber/SecuritySubscriber.php',
        'Subscriber/TwigSubscriber.php',
    ];

    public function testTheAdaptersStayOfAnAdaptersSize(): void
    {
        $above = [];
        foreach (['Security', 'Subscriber', 'Console/Command'] as $folder) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__).'/src/Omnibase/'.$folder, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $code = array_filter(file($file->getPathname()), static fn (string $line): bool => 1 !== preg_match('~^\s*$|^\s*(\*|/\*|//)~', $line));
                if (\count($code) > self::THRESHOLD) {
                    $above[] = substr($file->getPathname(), \strlen(\dirname(__DIR__).'/src/Omnibase/'));
                }
            }
        }
        sort($above);

        self::assertSame([], array_values(array_diff($above, self::STILL_ABOVE)), 'a class grew above an adapter\'s size');
        self::assertSame([], array_values(array_diff(self::STILL_ABOVE, $above)), 'a class was slimmed: take it out of STILL_ABOVE');
    }

    /** The slice-1 adapters (the voters) name no entity of omnibase's to decide on it: the policies do. */
    public function testTheVotersDecideNothingThemselves(): void
    {
        foreach (glob(\dirname(__DIR__).'/src/Omnibase/Security/Voter/*.php') as $file) {
            self::assertDoesNotMatchRegularExpression('~^use Base\\\\Entity\\\\~m', (string) file_get_contents($file), basename($file));
        }
    }

    /** user:create's token (omnibase 5.0's UserCreate::PASSWORD_CHANGE) is the one PasswordChangeSubscriber asks at the first sign-in. */
    public function testTheAccountsMadeFromTheConsoleChangeTheirPassword(): void
    {
        if (!class_exists(\Base\Console\Task\UserCreate::class)) {
            self::markTestSkipped('omnibase 4.x has no console tasks.');
        }

        self::assertSame(\Base\Console\Task\UserCreate::PASSWORD_CHANGE, \Omnibridge\Symfony\Omnibase\Subscriber\PasswordChangeSubscriber::TOKEN);
    }
}
