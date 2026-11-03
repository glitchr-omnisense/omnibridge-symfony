<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

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
