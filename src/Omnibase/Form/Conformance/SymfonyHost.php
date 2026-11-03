<?php

namespace Omnibridge\Symfony\Omnibase\Form\Conformance;

use Base\Field\Conformance\Host;
use Base\Field\Conformance\Outcome;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Symfony\Omnibase\Form\DefinitionForms;
use Symfony\Component\Form\FormInterface;
use Twig\Environment;

/**
 * Symfony as omnisense/omnibase's field conformance suite drives it
 * (Base\Field\Conformance\Suite): a definition made a Symfony form
 * (DefinitionForms), drawn by the application's form theme through Twig,
 * submitted and validated by Symfony Form.
 */
final class SymfonyHost implements Host
{
    public function __construct(private readonly DefinitionForms $forms, private readonly Environment $twig)
    {
    }

    public function name(): string
    {
        return 'symfony';
    }

    public function render(FormDefinition $form, mixed $data): string
    {
        return $this->twig->createTemplate('{{ form(form) }}')->render(['form' => $this->forms->create($form, $data)->createView()]);
    }

    public function submit(FormDefinition $form, array $submitted, mixed $data): Outcome
    {
        $symfony = $this->forms->create($form, $data);
        $symfony->submit($submitted);

        $errors = [];
        foreach ($symfony->getErrors(false) as $error) {
            $errors[''][] = $error->getMessage();
        }
        foreach ($symfony->all() as $name => $child) {
            foreach ($child->getErrors(true) as $error) {
                $errors[$name][] = $error->getMessage();
            }
        }

        return new Outcome(self::data($symfony), $errors);
    }

    private static function data(FormInterface $form): mixed
    {
        return $form->getData();
    }
}
