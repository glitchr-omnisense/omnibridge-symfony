<?php

namespace Omnibridge\Symfony\Omnibase\Legacy\DependencyInjection\Compiler\Pass;

// For omnisense/omnibase 4.x (a core without Base\Discovery): BaseBundle registers it in place of DiscoveryPass, which
// compiles the core's declaration of the same registry since step F5e slice 4c. Removed once 4.x is no longer served.

use Omnibridge\Symfony\Omnibase\DependencyInjection\Compiler\AbstractPass;
use Base\Service\Obfuscator;

class ObfuscatorCompressionPass extends AbstractPass
{
    public function serviceInterface(): ?string 
    { 
        return null;
    }

    public function taggedServiceIds(): string
    {
        return 'obfuscator.compression';
    }

    public function classFqcn(): string
    {
        return Obfuscator::class;
    }

    public function addMethod(): string
    {
        return 'addCompression';
    }
}
