<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Package\Model;

use Base\Database\Attribute\ResolveTargetEntity;

#[ResolveTargetEntity('App\Entity\User')]
interface OwnerInterface
{
}
