<?php

namespace Base\Controller\Attribute;

/**
 * An action's argument typed with an entity class, read from the route by the host (omnibase's record resolver:
 * Symfony's Omnibridge\Symfony\Omnibase\RecordArguments, Laravel's route binding): by default the route parameter
 * named like the argument, else `id`, as the entity's identifier; `id` names another route parameter, `mapping` route
 * parameters by the fields they match (['slug' => 'slug']), `expr` the finder of the repository and its arguments
 * (`oneBySlug(slug)`), `disabled` leaves the argument to the host. The neutral form of Symfony's #[MapEntity]: a
 * bundle's or a site's controller names nothing of Doctrine.
 *
 *     public function deed(Lease $lease, #[MapRecord(id: 'party')] LeaseParty $party): Response
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapRecord
{
    /** @param array<string, string>|null $mapping route parameter => field */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?array $mapping = null,
        public readonly ?string $expr = null,
        public readonly bool $disabled = false,
    ) {
    }
}
