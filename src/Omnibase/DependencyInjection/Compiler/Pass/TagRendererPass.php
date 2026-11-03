<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;

/**
 * The tag renderers (twig.tag_renderer: the HTML and Webpack renderers) handed to omnibase's Twig
 * environment. Nothing to do without Twig (plan "omnibase agnostic", F4): its service, named here
 * by its id, is defined only where Twig is installed (config/services/twig.php).
 */
class TagRendererPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'twig.tag_renderer';
    }

    public function classFqcn(): string
    {
        return 'Base\Twig\Environment';
    }

    public function addMethod(): string
    {
        return 'addRenderer';
    }
}
