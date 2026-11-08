<?php

namespace Omnibridge\Symfony\Omnibase\Form;

/**
 * A persisted to-many collection's owner and sides, from the persistence's shapes (FormMapping::collection(); plan
 * "omnibase agnostic", step B leftovers): what the association, select, file and attribute types read of Doctrine's
 * PersistentCollection::getMapping() and getOwner(), on any persistence.
 */
final class CollectionMapping
{
    /**
     * @param object      $owner      the entity holding the collection
     * @param string      $property   the association's property on the owner
     * @param string      $target     the class of its entries
     * @param bool        $owning     whether the owner's side owns the association (a many-to-many's owning side)
     * @param string|null $mappedBy   the entries' property the inverse side is mapped by
     * @param string|null $inversedBy the entries' property the owning side is inversed by
     */
    public function __construct(
        public readonly object $owner,
        public readonly string $property,
        public readonly string $target,
        public readonly bool $owning,
        public readonly ?string $mappedBy,
        public readonly ?string $inversedBy,
    ) {
    }

    /** Whether the owner's side is the inverse one (Doctrine's InverseSideMapping). */
    public function isInverseSide(): bool
    {
        return !$this->owning;
    }
}
