<?php

namespace Omnibridge\Symfony\Tests\Fixtures\Package\Entity;

use Base\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'package_item')]
class Item
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    public ?int $id = null;
}
