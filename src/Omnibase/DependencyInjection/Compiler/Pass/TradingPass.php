<?php

namespace Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\Pass;

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Trading;

class TradingPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): ?string
    {
        return 'currency.api';
    }

    public function classFqcn(): string
    {
        return Trading::class;
    }

    public function addMethod(): string
    {
        return 'addProvider';
    }
}
