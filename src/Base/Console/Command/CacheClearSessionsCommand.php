<?php

namespace Base\Console\Command;

use Base\Console\Task\CacheClearSessions;
use Base\Console\Task\CacheClearSessionsInput;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Base\Contract\UnitOfWork;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** cache:clear:sessions: Symfony's session folder (var/sessions) to omnibase's CacheClearSessions task (its plan, step F5e, slice 3). */
#[AsCommand(name: 'cache:clear:sessions', aliases: [], description: '')]
class CacheClearSessionsCommand extends Command
{
    /** @var string */
    protected string $projectDir;

    public function __construct(
        LocalizerInterface       $localizer,
        TranslatorInterface      $translator,
        ?UnitOfWork  $unitOfWork,
        ParameterBagInterface    $parameterBag,
        string                   $projectDir,
    )
    {
        parent::__construct($localizer, $translator, $unitOfWork, $parameterBag);
        $this->projectDir = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return (new CacheClearSessions())->run(new CacheClearSessionsInput($this->projectDir.'/var/sessions'), Reports::printer($input, $output))->status();
    }
}
