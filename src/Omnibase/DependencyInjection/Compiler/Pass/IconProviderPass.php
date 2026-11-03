<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;
use Base\Service\IconProvider;

class IconProviderPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'base.service.icon';
    }

    public function classFqcn(): string
    {
        return IconProvider::class;
    }

    public function addMethod(): string
    {
        return 'addAdapter';
    }
}
