<?php

namespace Omnibridge\Symfony\Static;

use Omnibridge\Static\Console\Cli;
use Omnibridge\Static\Render\RequestRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * omnibridge/static's build under Symfony: the same Console\Cli as the plain
 * command line, handed what this host offers the site's file - a renderer
 * through this kernel's own request handling (each page a visitor's GET, at
 * --render-origin, by default the router's default URI), the router's routes,
 * the container, the public folder.
 *
 *     bin/console omnibridge:static:build static.php --out=var/static [--verify] [--strict]
 *
 * Registered by config/services.php only where omnibridge/static is installed.
 */
#[AsCommand('omnibridge:static:build', 'Exports the site a static.php declares to a folder for a static host (omnibridge/static).')]
final class BuildCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('site', InputArgument::OPTIONAL, 'The file that declares the site (it returns an Omnibridge\Static\Site, or a closure that takes the host\'s offer)', 'static.php')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'The folder to export to (emptied first if it holds an earlier export)', 'var/static')
            ->addOption('render-origin', null, InputOption::VALUE_REQUIRED, 'The origin the pages are rendered at (the router\'s default URI if not given)')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Render every page twice; a page that differs is an error')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'A warning fails the build too')
            ->addOption('quiet-progress', null, InputOption::VALUE_NONE, 'Errors and counts only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $this->router->getContext();
        $origin = $input->getOption('render-origin');
        if (!\is_string($origin) || '' === $origin) {
            $port = 'https' === $context->getScheme() ? $context->getHttpsPort() : $context->getHttpPort();
            $origin = $context->getScheme().'://'.$context->getHost().(\in_array($port, [80, 443], true) ? '' : ':'.$port);
        }
        $kernel = $this->kernel;
        $host = [
            Cli::HOST_NAME => 'symfony',
            Cli::HOST_RENDERER => new RequestRenderer(static fn (Request $request) => $kernel->handle($request), $origin),
            Cli::HOST_ROUTES => $this->router->getRouteCollection(),
            Cli::HOST_CONTAINER => $this->kernel->getContainer(),
            Cli::HOST_PUBLIC_DIR => $this->kernel->getProjectDir().'/public',
        ];

        $options = array_filter([
            'out' => $input->getOption('out'),
            'verify' => $input->getOption('verify') ?: null,
            'strict' => $input->getOption('strict') ?: null,
            'quiet' => $input->getOption('quiet-progress') ?: null,
        ], static fn (mixed $value) => null !== $value);
        $site = $input->getArgument('site');
        $site = \is_string($site) ? $site : 'static.php';
        $file = str_starts_with($site, '/') ? $site : $this->kernel->getProjectDir().'/'.$site;

        $cli = new Cli(static function (string $line) use ($output): void {
            $output->writeln($line, OutputInterface::OUTPUT_RAW);
        });

        try {
            /** @var array<string, string|true> $options */
            return $cli->build($file, $options, $host);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::INVALID;
        }
    }
}
