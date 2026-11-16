<?php

namespace Base\Console\Command;

use Base\Attributes\AttributeReader;
use Base\Console\Task\UploaderImagesCrop;
use Base\Console\Task\UploaderImagesCropInput;
use Base\Database\Attribute\Uploader;
use Base\Database\UnitOfWorks;
use Base\Entity\Layout\Image;
use Base\Entity\Layout\ImageCrop;
use Base\Imagine\Filter\Basic\CropFilter;
use Base\Imagine\Filter\Format\BitmapFilter;
use Imagine\Image\Box;
use Base\BaseBundle;
use Base\Console\Command;
use Base\Console\Reports;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * uploader:images:crop: its options - and, to renormalise, the resolution chosen and the confirmation, asked
 * here - to omnibase's UploaderImagesCrop task (its plan, step F5e, slice 3), with the media controller, the
 * Report printed.
 */
#[AsCommand(name: 'uploader:images:crop', aliases: [], description: '')]
class UploaderImagesCropCommand extends UploaderImagesCommand
{
    protected $input;
    protected $output;
    protected $maxDefinition;
    protected $normalize;

    private \Base\Database\Repository\Entities $imageCropRepository;

    protected function configure(): void
    {
        $this->addOption('normalize', false, InputOption::VALUE_NONE, 'Do you want to update coordinate system ?');
        $this->addOption('max-definition', false, InputOption::VALUE_NONE, 'Which max definition to use ?');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $definition = null;
        if ($input->getOption('normalize')) {
            $definition = $this->getHelper('question')->ask($input, $output, new ChoiceQuestion('Please select a resolution class to be used for renormalization.', UploaderImagesCrop::definitions(), false));
            if (null === $definition) {
                return self::FAILURE;
            }
            $definition = new $definition();
        }

        $task = new UploaderImagesCrop($this->repositories, $this->mediaService, $this->defaultFormats, $this->unitOfWork, $this->mediaController);

        return $task->run(new UploaderImagesCropInput(
            $this->parameterBag->get('kernel.project_dir').'/src',
            $input->getOption('entity'),
            $input->getOption('property'),
            $input->getOption('uuid'),
            (bool) $input->getOption('orphans'),
            (bool) $input->getOption('delete-orphans'),
            (bool) $input->getOption('warmup'),
            (int) $input->getOption('batch'),
            $input->getOption('format') ?: null,
            null !== $definition,
            $definition,
        ), Reports::printer($input, $output), fn (string $question): bool => (bool) $this->getHelper('question')->ask($input, $output, new ConfirmationQuestion($question, false)))->status();
    }

    /**
     * @param mixed $class
     * @param string $field
     * @param Uploader $attribute
     * @param array $fileList
     * @return int|void
     */
    public function postProcess(mixed $class, string $field, Uploader $attribute, array $fileList)
    {
        if ($field != "source") {
            return parent::postProcess($class, $field, $attribute, $fileList);
        }

        if ($this->warmup) {

            $repository = UnitOfWorks::repositories($this->unitOfWork)->of($class);
            $images = $repository->findAll();
            $N = count($images);

            for ($i = 0; $i < $N; $i++) {
                $image = $images[$i];
                $imageCrops = $image->getCrops();

                if ($this->ibatch >= $this->batch && $this->batch > 0) {
                    $msg = ' [WARN] Batch limit reached out - Set the `--batch` limit higher, if you wish. ';
                    $this->output->writeln('');
                    $this->output->writeln('<warning,bkg>' . str_blankspace(strlen($msg)));
                    $this->output->writeln($msg);
                    $this->output->writeln(str_blankspace(strlen($msg)) . '</warning,bkg>');
                    $this->output->writeln('');

                    return Command::FAILURE;
                }

                $publicDir = $attribute->getFlysystem()->getPublic("", $attribute->storage());

                $file = $image->getSource();
                if ($file === null) {
                    $this->output->section()->writeln("\t           <warning>* Image #" . $image->getId() . " missing.</warning>", OutputInterface::VERBOSITY_VERBOSE);
                    continue;
                }

                $extensions = $this->mediaService->getExtensions($file);
                $extension = first($extensions);

                $dataWebp = $this->mediaService->image($file, ["webp" => true, "local_cache" => true], []);
                $data = $this->mediaService->image($file, ["webp" => false, "local_cache" => true, "extension" => $extension], []);
                if ($this->isCached($data)) {

                    $this->output->section()->writeln("             <warning>* Already cached main image \"." . str_lstrip(realpath($file), realpath($publicDir)) . "\" .. (" . ($i + 1) . "/" . $N . ")</warning>", OutputInterface::VERBOSITY_VERBOSE);

                } else {

                    $this->output->section()->writeln("             <ln>* Warming up main image \"." . str_lstrip(realpath($file), realpath($publicDir)) . "\" .. (" . ($i + 1) . "/" . $N . ")</ln>", OutputInterface::VERBOSITY_VERBOSE);
                    $this->output->section()->writeln("                - Memory usage: " . round(memory_get_usage() / 1024 / 1024) . "MB; File: " . implode(", ", $attribute->mimeTypes()) . " (incl. WEBP); ", OutputInterface::VERBOSITY_DEBUG);

                    $this->mediaController->ImageWebp($dataWebp);
                    $this->mediaController->Image($data, $extension);
                    
                    $this->ibatch++;
                }

                foreach ($imageCrops as $imageCrop) {
                    $identifier = $imageCrop->getSlug() ?? $imageCrop->getWidth() . "x" . $imageCrop->getHeight();
                    if ($this->isCached($data, $imageCrop)) {
                        $this->output->section()->writeln("             <warning>  Already cached \"" . str_lstrip($file, $publicDir) . "\" (" . $identifier . ") .. (" . ($i + 1) . "/" . $N . ")</warning>", OutputInterface::VERBOSITY_VERBOSE);
                    } else {
                        $this->output->section()->writeln("             <ln>  Warming up \"" . str_lstrip($file, $publicDir) . "\" (" . $identifier . ") .. (" . ($i + 1) . "/" . $N . ")</ln>", OutputInterface::VERBOSITY_VERBOSE);
                        $this->ibatch++;

                        $this->mediaController->ImageCrop($dataWebp, $identifier, $extension);
                        $this->mediaController->ImageCrop($data, $identifier, $extension);
                    }

                    $this->output->section()->writeln("                - Memory usage: " . round(memory_get_usage() / 1024 / 1024) . "MB; File: " . implode(", ", $attribute->mimeTypes()) . " (incl. WEBP); " . $identifier, OutputInterface::VERBOSITY_DEBUG);
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param $data
     * @param $imageCrop
     * @return bool
     */
    public function isCached($data, $imageCrop = null)
    {
        if ($imageCrop == null) {
            return parent::isCached($data);
        }

        //
        // Extract parameters
        $args = $this->mediaService->resolve($data);
        if (!$args) {
            return false;
        }

        $filters = $args["filters"] ?? [];
        $options = $args["options"] ?? [];
        $path = $args["path"] ?? null;
        if ($path == null) {
            return false;
        }

        // Dimension information
        $imagesize = getimagesize($path);
        $naturalWidth = $imagesize[0] ?? 0;
        if ($naturalWidth == 0) {
            return true;
        }
        $naturalHeight = $imagesize[1] ?? 0;
        if ($naturalHeight == 0) {
            return true;
        }

        //
        // Apply filter
        // NB: Only applying cropping if ImageCrop is found ..
        //     .. otherwise some naughty users might be generating infinite amount of image
        array_prepend($filters, new CropFilter(
            $imageCrop->getX0(),
            $imageCrop->getY0(),
            $imageCrop->getWidth0(),
            $imageCrop->getHeight0()
        ));

        $localCache = array_pop_key("local_cache", $options);
        $localCache = $args["local_cache"] ?? $localCache;

        return $this->mediaService->isCached($path, ["local_cache" => $localCache], new BitmapFilter(null, $filters, $options));
    }
}
