<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

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
