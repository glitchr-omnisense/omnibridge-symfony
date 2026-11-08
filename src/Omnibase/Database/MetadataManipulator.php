<?php

namespace Omnibridge\Symfony\Omnibase\Database;

/*
 * What the form types, the field types and the services of this bridge read the entities' mapping with (plan "omnibase
 * agnostic", step B3): omnisense/omnibase 5.x's Base\Database\Mapping\MetadataManipulator - on the persistence's shapes,
 * whatever the persistence; omnibridge/doctrine's ClassMetadataManipulator extends it -, or on 4.x, which this bridge
 * serves too, omnibridge/doctrine's ClassMetadataManipulator itself. What only Doctrine's answers (getClassMetadata(),
 * getMapping(), getEntityManager()...) is asked where Doctrine is the persistence. An omnibridge/doctrine older than step E3
 * (its manipulator not the core's) keeps Doctrine's.
 */
$doctrine = \Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator::class;
if (class_exists(\Base\Database\Mapping\MetadataManipulator::class) && (!class_exists($doctrine) || is_subclass_of($doctrine, \Base\Database\Mapping\MetadataManipulator::class))) {
    class_alias(\Base\Database\Mapping\MetadataManipulator::class, MetadataManipulator::class);
} else {
    class_alias($doctrine, MetadataManipulator::class);
}
