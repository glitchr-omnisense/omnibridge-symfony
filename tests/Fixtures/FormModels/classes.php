<?php

// A type of omnibase moved to this bridge whose model stayed in the core (as ContactType's ContactModel), and a site's
// type with its model beside it: the core is not installed here, so its namespace gets a model of the test's own.

namespace Base\Form\Model {
    class FormModelsProbeModel
    {
    }
}

namespace Omnibridge\Symfony\Omnibase\Form\Type {
    class FormModelsProbeType extends \Omnibridge\Symfony\Omnibase\Form\Common\AbstractType
    {
    }

    class FormModelsOrphanType extends \Omnibridge\Symfony\Omnibase\Form\Common\AbstractType
    {
    }
}

namespace App\Form\Type {
    class FormModelsSiteType extends \Omnibridge\Symfony\Omnibase\Form\Common\AbstractType
    {
    }
}

namespace App\Form\Model {
    class FormModelsSiteModel
    {
    }
}
