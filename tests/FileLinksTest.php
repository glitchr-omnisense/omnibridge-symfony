<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\FileLinks;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

/**
 * The file links of the error pages, on the host when the site runs in a
 * container (APP_PATH): Symfony's own FileLinkFormatter on a mapped format,
 * by decorating debug.file_link_formatter - no class extending the @final
 * formatter, no patch of symfony/error-handler (plan "omnibase agnostique", P3; here since A1).
 */
final class FileLinksTest extends TestCase
{
    private array $server;
    private array $env;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->env = $_ENV;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_ENV = $this->env;
    }

    public function testTheFormatMapsTheContainerToTheHost(): void
    {
        self::assertSame('vscode://file/%f:%l&/srv/app/>/Users/me/site/', FileLinks::format(null, '/srv/app', '/Users/me/site'));
        self::assertSame('phpstorm://open?file=%f&line=%l&/srv/app/>/Users/me/site/', FileLinks::format('phpstorm', '/srv/app/', '/Users/me/site/'));
        self::assertSame('idea://%f:%l&/srv/app/>/Users/me/site/', FileLinks::format('idea://%f:%l', '/srv/app', '/Users/me/site'));
    }

    public function testTheFormerSyntaxOfTheSitesIsReadAsSymfonys(): void
    {
        // framework.ide of the sites: "vscode://file/%%f[%%b:%env(APP_PATH)%]:%%l".
        self::assertSame('vscode://file/%f:%l&/srv/app/>/Users/me/site/', FileLinks::format('vscode://file/%f[%b:/Users/me/site]:%l', '/srv/app', null));
        self::assertSame('vscode://file/%f:%l&/srv/app/>/Users/me/site/', FileLinks::format('vscode://file/%f[%b:]:%l', '/srv/app', '/Users/me/site'));
        self::assertSame('vscode://file/%f:%l', FileLinks::format('vscode://file/%f[%b:]:%l', '/srv/app', null));

        $formatter = FileLinks::formatter(new FileLinkFormatter(''), 'vscode://file/%f[%b:/Users/me/site]:%l', '/srv/app', '/Users/me/site');
        self::assertSame('vscode://file//Users/me/site/src/Kernel.php:7', $formatter->format('/srv/app/src/Kernel.php', 7));
    }

    public function testNothingToMap(): void
    {
        self::assertNull(FileLinks::format(null, '/srv/app', null));
        self::assertNull(FileLinks::format('', '/srv/app', ''));
        self::assertSame('vscode', FileLinks::format('vscode', '/Users/me/site', '/Users/me/site'));
        self::assertSame('vscode://file/%f:%l&/a/>/b/', FileLinks::format('vscode://file/%f:%l&/a/>/b/', '/srv/app', '/Users/me/site'));
    }

    public function testTheDecorationLinksToTheHost(): void
    {
        $inner = new FileLinkFormatter('');
        $formatter = FileLinks::formatter($inner, null, '/srv/app', '/Users/me/site');

        self::assertNotSame($inner, $formatter);
        self::assertSame('vscode://file//Users/me/site/src/Entity/User.php:12', $formatter->format('/srv/app/src/Entity/User.php', 12));
        self::assertSame($inner, FileLinks::formatter($inner, null, '/srv/app', null));
    }

    public function testAnErrorPageLinksToTheHost(): void
    {
        // This file as if it were in a container's project directory.
        $renderer = new HtmlErrorRenderer(true, null, FileLinks::formatter(null, null, __DIR__, '/Users/me/site'), __DIR__);
        $page = $renderer->render(new \RuntimeException('forced'))->getAsString();

        self::assertStringContainsString('href="vscode://file//Users/me/site/FileLinksTest.php:', $page);
    }

    public function testExportGivesTheRenderersBuiltOutsideTheContainerTheMapping(): void
    {
        unset($_SERVER['SYMFONY_IDE'], $_ENV['SYMFONY_IDE']);
        $_SERVER['APP_PATH'] = '/Users/me/site';
        FileLinks::export('/srv/app');

        self::assertSame('vscode://file/%f:%l&/srv/app/>/Users/me/site/', $_SERVER['SYMFONY_IDE']);
        self::assertSame('vscode://file//Users/me/site/bin/console:3', (new FileLinkFormatter())->format('/srv/app/bin/console', 3));

        $_SERVER['SYMFONY_IDE'] = 'phpstorm';
        FileLinks::export('/srv/app');
        self::assertSame('phpstorm', $_SERVER['SYMFONY_IDE']);
    }
}
