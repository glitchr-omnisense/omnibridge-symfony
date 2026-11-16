<?php

namespace App\Entity;

use Base\Mapping as ORM;

/**
 * An entity written in omnibase's own vocabulary (Base\Mapping, E3), as every site's entities are: Doctrine's
 * attribute driver knows nothing of it, omnibridge/doctrine's DeclarationDriver reads it - the site's doctrine.yaml
 * says only where the files are (`mappings: App: { dir: src/Entity, prefix: App\Entity }`).
 */
#[ORM\Entity]
#[ORM\Table(name: 'host_note')]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    public ?int $id = null;

    #[ORM\Column(type: 'string', length: 80)]
    public string $title = '';
}
