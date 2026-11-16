<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Host;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/** The bundle whose extension is `base:` in the test's application (HostExtension). */
final class BaseHostBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        return new HostExtension();
    }
}
