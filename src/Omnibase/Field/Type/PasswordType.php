<?php

namespace Omnibridge\Symfony\Omnibase\Field\Type;

use Base\Service\Model\AutovalidateInterface;

use Twig\Environment;
use Base\Validator\Constraints\Password;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\PasswordType as SymfonyPasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

class PasswordType extends AbstractType implements AutovalidateInterface, DataMapperInterface
{
    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var Environment
     */
    protected Environment $twig;

    public function __construct(TranslatorInterface $translator, Environment $twig)
    {
        $this->translator = $translator;
        $this->twig = $twig;
    }

    public function getBlockPrefix(): string
    {
        return 'password2';
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars["inline"] = $options["inline"];
        $view->vars["hint"] = $options["hint"];
        $view->vars["secure"] = $options["secure"];
        $view->vars["repeater"] = $options["repeater"];
        $view->vars["revealer"] = $options["revealer"];
        $view->vars["min_length"] = $options["min_length"];
        $view->vars["min_strength"] = $options["min_strength"];
        $view->vars["max_strength"] = $options["max_strength"];
        $view->vars["autocomplete"] = $options["autocomplete"];
        $view->vars["suggestions"] = $options["suggestions"];
        $view->vars["required"] = $options["required"];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'inline' => true,
            'secure' => true,
            'hint' => true,
            'revealer' => false,
            'repeater' => true,
            'autocomplete' => false,
            'suggestions' => false,
            "min_length" => Password::MIN_LENGTH_FALLBACK,
            "min_strength" => Password::MIN_STRENGTH_FALLBACK,
            "max_strength" => Password::MAX_STRENGTH_FALLBACK,
            "required" => false,
            'options' => [],
            'options[repeater]' => [],
            'invalid_message' => '@fields.password.invalid_message'
        ]);

        $resolver->setNormalizer('revealer', function (Options $options, $value) {
            return $value || !$options["repeater"];
        });
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        if (!isset($options['options']['error_bubbling'])) {
            $options['options']['error_bubbling'] = $options['error_bubbling'];
        }
        if (!isset($options['options']['help'])) {
            $options["options"]["help"] = $options["help"];
        }
        if (!isset($options['options']['required']) && array_key_exists("required", $options)) {
            $options["options"]["required"] = $options["required"];
        }

        $builder->add('plain', SymfonyPasswordType::class, array_merge([
            "label" => $options["label"] ?? $this->translator->trans("@fields.password.first"),
            "mapped" => true,
            "constraints" => [new Password(
                $options["require_uppercase"] ?? true,
                $options["require_lowercase"] ?? true,
                $options["require_numbers"] ?? true,
                $options["require_specials"] ?? true,
                $options["require_length"] ?? true,
                $options["min_strength"],
                $options["min_length"])]
        ], $options["options"]));

        if ($options["repeater"]) {
            $builder->add('plain_repeater', SymfonyPasswordType::class, array_merge([
                "label" => $this->translator->trans("@fields.password.second"),
                "mapped" => true
            ], $options['options[repeater]']));
        }
    }

    /**
     * @param $viewData
     * @param Traversable $forms
     * @return void
     */
    public function mapDataToForms($viewData, Traversable $forms): void
    {
        $plainPasswordType = iterator_to_array($forms)["plain"];
        if ($plainPasswordType) {
            $plainPasswordType->setData($viewData);
        }
    }

    /**
     * @param Traversable $forms
     * @param $viewData
     * @return void
     */
    public function mapFormsToData(Traversable $forms, &$viewData): void
    {
        $plainPasswordType = iterator_to_array($forms)["plain"] ?? null;
        if ($plainPasswordType == null) {
            throw new TransformationFailedException("Missing password field.");
        }

        $plainPasswordRepeaterType = iterator_to_array($forms)["plain_repeater"] ?? null;
        $options = $plainPasswordType->getConfig()->getOptions();

        if ($plainPasswordRepeaterType) {
            if ($plainPasswordType->getViewData() !== $plainPasswordRepeaterType->getViewData()) {
                throw new TransformationFailedException('Password are differents');
            }
        }

        $viewData = $plainPasswordType->getViewData();
        if (!$viewData) {
            $viewData = null;
        }
    }
}
