<?php

namespace Omnibridge\Symfony\Omnibase\Console;

use Base\Database\UnitOfWorks;
use Base\Service\LocalizerInterface;
use Base\Service\ParameterBagInterface;
use Base\Service\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class Command extends SymfonyCommand
{
    /**
     * @var LocalizerInterface
     */
    protected LocalizerInterface $localizer;
    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;
    /**
     * @var EntityManagerInterface
     *
     * @deprecated since step F2d of the plan "omnibase agnostic", ask $unitOfWork (and UnitOfWorks::repositories()); removed at 6.0
     */
    protected $entityManager;
    /** The unit of work over the entity manager given (plan "omnibase agnostic", step F2d), else the persistence's chosen (setUnitOfWork()). */
    protected UnitOfWork $unitOfWork;
    /**
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;
    /**
     * @var PropertyAccessor
     */
    protected PropertyAccessor $propertyAccessor;

    /**
     * The entity manager is Doctrine's where it is there (null without Doctrine, plan "omnibase agnostic", step B3: the
     * unit of work is then the persistence's, given by setUnitOfWork()).
     */
    public function __construct(LocalizerInterface $localizer, TranslatorInterface $translator, ?EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag)
    {
        $this->localizer = $localizer;
        $this->translator = $translator;
        $this->entityManager = $entityManager;
        if (null !== $entityManager) {
            $this->unitOfWork = UnitOfWorks::of($entityManager);
        }
        $this->parameterBag = $parameterBag;

        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        parent::__construct();
    }

    /**
     * The unit of work of the persistence the host chose (omnisense/omnibase's plan "omnibase agnostic", steps F7f and
     * B4): the container's, given after the constructor's entity manager - Doctrine's over the same entity manager on
     * doctrine, omnibridge/eloquent's on eloquent, so that a command's reads and writes go where the site's do. Every
     * command declared on Omnibridge\Symfony\Omnibase\Console\Command's definition gets it; the commands named after Doctrine use
     * getEntityManager() itself.
     */
    public function setUnitOfWork(UnitOfWork $unitOfWork): static
    {
        $this->unitOfWork = $unitOfWork;

        return $this;
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    protected function getParameterBag(): ParameterBagInterface
    {
        return $this->parameterBag;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager ?? throw new \LogicException(sprintf('The command "%s" asks Doctrine\'s entity manager, and Doctrine is not installed here (omnibridge/doctrine): it runs on Doctrine only.', $this->getName()));
    }

    protected function getLocalizer(): LocalizerInterface
    {
        return $this->localizer;
    }

    protected function configure(): void
    {
        $this->addOption('purpose', null, InputOption::VALUE_OPTIONAL, 'Show command description ?');
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $output->getFormatter()->setStyle('info', new OutputFormatterStyle('green', null, []));
        $output->getFormatter()->setStyle('bold,info', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('info,bold', new OutputFormatterStyle('green', null, ['bold']));
        $output->getFormatter()->setStyle('info,bkg', new OutputFormatterStyle('black', 'green'));
        $output->getFormatter()->setStyle('bkg,info', new OutputFormatterStyle('black', 'green'));

        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow', null, []));
        $output->getFormatter()->setStyle('warning,bold', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('bold,warning', new OutputFormatterStyle('yellow', null, ['bold']));
        $output->getFormatter()->setStyle('warning,bkg', new OutputFormatterStyle('black', 'yellow'));
        $output->getFormatter()->setStyle('bkg,warning', new OutputFormatterStyle('black', 'yellow'));

        $output->getFormatter()->setStyle('red', new OutputFormatterStyle('red', null, []));
        $output->getFormatter()->setStyle('red,bold', new OutputFormatterStyle('red', null, ['bold']));
        $output->getFormatter()->setStyle('bold,red', new OutputFormatterStyle('red', null, ['bold']));
        $output->getFormatter()->setStyle('red,bkg', new OutputFormatterStyle(null, 'red'));
        $output->getFormatter()->setStyle('bkg,red', new OutputFormatterStyle(null, 'red'));

        $output->getFormatter()->setStyle('ln', new OutputFormatterStyle('cyan', null, []));
        $output->getFormatter()->setStyle('ln,bold', new OutputFormatterStyle('cyan', null, ['bold']));
        $output->getFormatter()->setStyle('bold,ln', new OutputFormatterStyle('cyan', null, ['bold']));
        $output->getFormatter()->setStyle('ln,bkg', new OutputFormatterStyle(null, 'cyan'));
        $output->getFormatter()->setStyle('bkg,ln', new OutputFormatterStyle(null, 'cyan'));

        $output->getFormatter()->setStyle('magenta', new OutputFormatterStyle('magenta', null, []));
        $output->getFormatter()->setStyle('magenta,bold', new OutputFormatterStyle('magenta', null, ['bold']));
        $output->getFormatter()->setStyle('bold,magenta', new OutputFormatterStyle('magenta', null, ['bold']));
        $output->getFormatter()->setStyle('magenta,bkg', new OutputFormatterStyle(null, 'magenta'));
        $output->getFormatter()->setStyle('bkg,magenta', new OutputFormatterStyle(null, 'magenta'));

        $this->SetDescription(get_called_class()::GetDescription());
        if ($this->GetDescription() && $input->hasArgument("purpose")) {
            $output->section()->writeln("\n // Command purpose :");
            foreach (explode("\n", $this->GetDescription()) as $line) {
                $output->section()->writeln(" // \t" . trim($line));
            }

            $output->section()->writeln(" // \n");
        }

        return parent::run($input, $output);
    }
}
