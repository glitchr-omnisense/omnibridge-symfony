<?php

namespace App\Entity;

use Base\Mapping as ORM;

/** An application's comment (lesringostars' App\Entity\Article\Comment), its repository a ThreadRepository of its own. */
#[ORM\Entity]
#[ORM\Table(name: 'host_comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    public string $content = '';
}
