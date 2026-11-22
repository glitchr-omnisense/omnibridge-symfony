<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Package\Entity\Off;

use Base\Mapping as ORM;

/** A feature's entity standing on a package that is not installed: left out (base.entity_folders.exclude). */
#[ORM\Entity]
class Thing extends \Omnibridge\Symfony\Tests\Fixtures\Missing\BillingPlan
{
}
