<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

use Base\Database\Entity\EntityExtension;
use Base\Database\Entity\EntityExtensionInterface;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;
use Dom\Entity;

class EntityExtensionPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return EntityExtensionInterface::class;
    }

    public function taggedServiceIds(): ?string
    {
        return null;
    }

    public function classFqcn(): string
    {
        return EntityExtension::class;
    }

    public function addMethod(): string
    {
        return 'addExtension';
    }
}
