<?php

namespace Omnibridge\Symfony\Tests\Static;

use Omnibridge\Static\Console\Cli;
use Omnibridge\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * bin/console omnibridge:static:build hands omnibridge/static's command line its export options: --cache keeps the
 * renderer's answers between builds (a page whose declared inputs did not change is not rendered again), --only
 * renders the addresses it names whatever the cache holds, --full renders everything. The site's file is a closure
 * over the host's offer; its pages say which host rendered them, and each declares the content file it shows.
 */
final class BuildCommandTest extends KernelTestCase
{
    private string $dir;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        if (!class_exists(Cli::class)) {
            self::markTestSkipped('omnibridge/static is not installed.');
        }
        $this->dir = sys_get_temp_dir().'/omnibridge-symfony-static-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/content', 0777, true);
        file_put_contents($this->dir.'/content/a.txt', 'A');
        file_put_contents($this->dir.'/content/b.txt', 'B');
        file_put_contents($this->dir.'/static.php', <<<'PHP'
            <?php

            use Omnibridge\Static\Console\Cli;
            use Omnibridge\Static\Render\CallableRenderer;
            use Omnibridge\Static\Route\PageList;
            use Omnibridge\Static\Site;
            use Omnibridge\Static\StaticPage;

            return static function (array $host): Site {
                $file = static fn (StaticPage $page): string => __DIR__.'/content/'.('/' === $page->path ? 'a' : ltrim($page->path, '/')).'.txt';

                return (new Site('https://example.org'))
                    ->provide(new PageList(['/', '/b']))
                    ->renderWith(new CallableRenderer(static fn (StaticPage $page): string => sprintf(
                        '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%s</title></head><body>%s by %s, %d routes</body></html>',
                        $page->path,
                        file_get_contents($file($page)),
                        $host[Cli::HOST_NAME],
                        count($host[Cli::HOST_ROUTES]),
                    )))
                    ->dependsOn([], static fn (StaticPage $page): array => [$file($page)]);
            };
            PHP);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->dir) && is_dir($this->dir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                \assert($file instanceof \SplFileInfo);
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->dir);
        }
    }

    public function testTheExportOptionsReachTheCommandLine(): void
    {
        $command = (new Application(self::bootKernel()))->find('omnibridge:static:build');
        $build = function (array $options) use ($command): string {
            $tester = new CommandTester($command);
            $status = $tester->execute(['site' => $this->dir.'/static.php', '--out' => $this->dir.'/out'] + $options);
            self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());

            return $tester->getDisplay();
        };
        $cache = ['--cache' => $this->dir.'/cache'];

        self::assertStringContainsString('incremental: 2 rendered, 0 reused;', $build($cache));
        self::assertStringContainsString('A by symfony, 2 routes', (string) file_get_contents($this->dir.'/out/index.html'), 'the host\'s offer reaches the site\'s file');
        self::assertDirectoryExists($this->dir.'/cache');

        // --cache: nothing changed, nothing rendered.
        self::assertStringContainsString('incremental: 0 rendered, 2 reused;', $build($cache));

        // A page's declared input changed: that page alone.
        file_put_contents($this->dir.'/content/b.txt', 'B, changed');
        self::assertStringContainsString('incremental: 1 rendered, 1 reused;', $build($cache));
        self::assertStringContainsString('B, changed by symfony', (string) file_get_contents($this->dir.'/out/b/index.html'));

        // --only: the address it names, whatever the cache holds.
        self::assertStringContainsString('incremental: 1 rendered, 1 reused;', $build($cache + ['--only' => '/']));

        // --full: everything, the cache aside.
        self::assertStringContainsString('full (asked): 2 rendered, 0 reused;', $build($cache + ['--full' => true]));

        // Without --cache, nothing is reused.
        self::assertStringContainsString('incremental: 2 rendered, 0 reused;', $build([]));
    }

    public function testAnEmptyCacheFolderIsInvalid(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('omnibridge:static:build'));

        self::assertSame(Command::INVALID, $tester->execute(['site' => $this->dir.'/static.php', '--out' => $this->dir.'/out', '--cache' => '']));
        self::assertStringContainsString('--cache names a folder', $tester->getDisplay());
        self::assertFileDoesNotExist($this->dir.'/out/index.html');
    }
}
