<?php

namespace Base\Form\Traits;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;

interface FormGuessInterface
{
    public const GUESS_FROM_FORM = "GUESS_FROM_FORM";
    public const GUESS_FROM_PHPDOC = "GUESS_FROM_PHPDOC";
    public const GUESS_FROM_DATA = "GUESS_FROM_DATA";
    public const GUESS_FROM_VIEW = "GUESS_FROM_VIEW";

    public function guessClass(FormInterface|FormEvent $form, ?array $options = null): ?string;

    public function guessMultiple(FormInterface|FormEvent|FormBuilderInterface $form, ?array $options = null);

    public function guessSortable(FormInterface|FormEvent|FormBuilderInterface $form, ?array $options = null);

    public function guessChoices(FormInterface|FormBuilderInterface $form, ?array $options = null);

    public function guessChoiceAutocomplete(FormInterface|FormBuilderInterface $form, ?array $options = null);

    /**
     * @param FormInterface|FormBuilderInterface $form
     * @param array|null $options
     * @param $data
     * @return mixed
     */
    public function guessChoiceFilter(FormInterface|FormBuilderInterface $form, ?array $options = null, $data = null);
}
