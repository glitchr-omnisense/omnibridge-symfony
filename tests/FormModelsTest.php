<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Symfony\Omnibase\Form\Common\FormModels;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/Fixtures/FormModels/classes.php';

/**
 * A form type's model for the option `use_model`: omnibase's types moved to this bridge
 * (Omnibridge\Symfony\Omnibase\Form\Type\*) and their models stayed in the core (Base\Form\Model\*), so the model named
 * beside a moved type does not exist - the contact page of a site naming ContactType by its new name answered 500
 * ("Form model Omnibridge\Symfony\Omnibase\Form\Model\ContactModel not found"). The core's model is found; a site's
 * model beside its type is found as before; a type with no model anywhere keeps the name beside it, for the message.
 * The core's own models against the moved types: its tests/Form/FormModelsTest.
 */
#[Group('without-omniauth')]
final class FormModelsTest extends TestCase
{
    public function testAMovedTypeFindsItsModelInTheCore(): void
    {
        self::assertSame('Base\Form\Model\FormModelsProbeModel', \Omnibridge\Symfony\Omnibase\Form\Type\FormModelsProbeType::getModelClass());
        self::assertSame('Base\Form\Model\FormModelsProbeModel', FormModels::resolve('Omnibridge\Symfony\Omnibase\Form\Model\FormModelsProbeModel'));
    }

    public function testASiteModelBesideItsTypeIsFoundAsBefore(): void
    {
        self::assertSame('App\Form\Model\FormModelsSiteModel', \App\Form\Type\FormModelsSiteType::getModelClass());
    }

    public function testATypeWithoutModelKeepsTheNameBesideIt(): void
    {
        self::assertSame('Omnibridge\Symfony\Omnibase\Form\Model\FormModelsOrphanModel', \Omnibridge\Symfony\Omnibase\Form\Type\FormModelsOrphanType::getModelClass());
        self::assertSame('App\Form\Model\NoSuchModel', FormModels::resolve('App\Form\Model\NoSuchModel'));
    }
}
