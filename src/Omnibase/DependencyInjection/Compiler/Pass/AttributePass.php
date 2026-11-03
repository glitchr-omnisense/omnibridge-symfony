<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Base\Attributes\AttributeReader;
use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;

class AttributePass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'base.attribute';
    }

    public function classFqcn(): string
    {
        return AttributeReader::class;
    }

    public function addMethod(): string
    {
        return 'addAttribute';
    }
}
