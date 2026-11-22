<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Excluded;

/**
 * A class whose parent is another package's, which is not installed: loading it is a fatal error. Symfony keeps an
 * abstract definition tagged container.excluded for such a file a resource's `exclude` left out (DiscoveryPassTest).
 */
class OffFeature extends \Omnibridge\Symfony\Tests\Fixtures\Missing\BillingPlan implements \Countable
{
    public function count(): int
    {
        return 0;
    }
}
