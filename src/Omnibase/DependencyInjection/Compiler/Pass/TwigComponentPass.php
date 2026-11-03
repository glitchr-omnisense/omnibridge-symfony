<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\Definition;

class TwigComponentPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $projectDir = $container->getParameter('kernel.project_dir');

        // Two namespaces to scan:
        $namespaces = [
            'Base\\Twig\\Component\\' => $projectDir . '/src/Twig/Component',
            'App\\Twig\\Component\\'  => \Omnibridge\Symfony\Omnibase\BaseBundle::getBundleDir() . '/src/Twig/Component'
        ];

        foreach ($namespaces as $namespace => $directory) {
            $this->loadNamespace($container, $namespace, $directory);
        }
    }

    private function loadNamespace(ContainerBuilder $container, string $namespace, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.php');

        foreach ($finder as $file) {

            $relative = $file->getRelativePathname();
            $short = substr($relative, 0, -4); // remove ".php"
            $className = $namespace . str_replace('/', '\\', $short);

            if (!class_exists($className)) {
                continue;
            }

            $ref = new \ReflectionClass($className);
            if ($ref->isAbstract()) {
                continue;
            }

            // Convert PHP class → component short name
            $basename = $ref->getShortName();
            $component = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $basename));

            // Determine prefix automatically
            if (str_starts_with($className, 'Base\\')) {
                $component = 'base:' . $component;
            } elseif (str_starts_with($className, 'App\\')) {
                $component = 'app:' . $component;
            }

            // Register service definition
            $def = new Definition($className);
            $def->setAutowired(true);
            $def->setAutoconfigured(true);
            $def->setPublic(false);

            // Tag as TwigComponent 2.x
            $def->addTag('twig.component', [
                'key' => $component,
            ]);

            $container->setDefinition($className, $def);
        }
    }
}