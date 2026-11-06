<?php

namespace Omnibridge\Symfony\Omnibase\Console;

use Base\Console\Report;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * omnibase's Report (its plan "omnibase agnostic", step F5e, slice 3: Base\Console\Task\*) printed on Symfony's
 * console as the task says it: text as it is at its verbosity, PSR-3 levels as SymfonyStyle's blocks, a table as
 * Table draws it. omnibridge/laravel prints it the same way on artisan's output (a SymfonyStyle too).
 */
final class Reports
{
    /** The printer of a task's entries, as they come. */
    public static function printer(InputInterface $input, OutputInterface $output): \Closure
    {
        foreach (Report::STYLES as $name => [$foreground, $background, $options]) {
            if (!$output->getFormatter()->hasStyle($name)) {
                $output->getFormatter()->setStyle($name, new OutputFormatterStyle($foreground, $background, $options));
            }
        }
        $io = new SymfonyStyle($input, $output);

        return static function (array $entry) use ($io): void {
            match ($entry['kind']) {
                Report::TEXT => $io->write($entry['text'], $entry['newline'], $entry['verbosity']),
                Report::SUCCESS_BLOCK => $io->success($entry['text']),
                Report::TABLE => (new Table($io))->setHeaders($entry['headers'])->setRows($entry['rows'])->render(),
                'emergency', 'alert', 'critical', 'error' => $io->error($entry['text']),
                'warning' => $io->warning($entry['text']),
                'notice' => $io->note($entry['text']),
                'info' => $io->info($entry['text']),
                default => $io->comment($entry['text']),
            };
        };
    }
}
