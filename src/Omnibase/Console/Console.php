<?php

namespace Omnibridge\Symfony\Omnibase\Console;

use Base\Console\ConsoleInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\KernelInterface;

class Console implements ConsoleInterface
{
    protected Application $application;

    public function __construct(KernelInterface $kernel, int $verbosity = Output::VERBOSITY_NORMAL)
    {
        $this->application = new Application($kernel);
        $this->application->setAutoExit(false);

        $this->verbosity = $verbosity;
    }

    protected int $verbosity;

    /**
     * @param int $verbosity
     * @return $this
     */
    public function verbosity(int $verbosity)
    {
        $this->verbosity = $verbosity;
        return $this;
    }

    public function exec(string $command, array $parameters = []): ?string
    {
        $input = array_merge($parameters, ['command' => $command]);
        $input = array_transforms(fn($k, $v): array => str_starts_with($v, "-") ? [$v, true] : [$k, $v], $input);
        $input = new ArrayInput($input);

        $output = is_cli() ? new ConsoleOutput($this->verbosity) : new BufferedOutput($this->verbosity);
        $this->application->run($input, $output);
        if ($output instanceof BufferedOutput) {
            return $output->fetch();
        }

        return null;
    }
}
