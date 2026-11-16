<?php

namespace Base\Console;

use Symfony\Component\Console\Output\OutputInterface;

class Application extends \Symfony\Bundle\FrameworkBundle\Console\Application
{
    protected function doRenderThrowable(\Throwable $e, OutputInterface $output): void
    {
        $projectDir = $this->getKernel()->getProjectDir();

        // loop through the exception and its previous exceptions
        for ($current = $e; $current; $current = $current->getPrevious()) {
            $this->makePathsRelative($current, $projectDir);
        }

        parent::doRenderThrowable($e, $output);
    }

    private function makePathsRelative(\Throwable $e, string $projectDir): void
    {
        $ref = new \ReflectionClass($e);

        // adjust the "file" property
        if ($ref->hasProperty('file')) {
            $prop = $ref->getProperty('file');
            $prop->setAccessible(true);
            $file = $prop->getValue($e);
            if (0 === strpos($file, $projectDir)) {
                $prop->setValue($e, "./".substr($file, strlen($projectDir) + 1));
            }
        }

        // adjust the "trace" property
        if ($ref->hasProperty('trace')) {
            $prop = $ref->getProperty('trace');
            $prop->setAccessible(true);
            $trace = $prop->getValue($e);
            foreach ($trace as &$frame) {
                if (isset($frame['file']) && 0 === strpos($frame['file'], $projectDir)) {
                    $frame['file'] = "./".substr($frame['file'], strlen($projectDir) + 1);
                }
            }
            $prop->setValue($e, $trace);
        }
    }
}
