<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Sharing;

class SharingPass extends AbstractPass
{
    public function serviceInterface(): ?string
    {
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'base.service.sharing';
    }

    public function classFqcn(): string
    {
        return Sharing::class;
    }

    public function addMethod(): string
    {
        return 'addAdapter';
    }
}
