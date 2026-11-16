<?php

namespace Base\Form;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface FormProcessorInterface
{
    public function handleRequest(Request $request): static;

    public function hydrate(mixed $data): mixed;

    public function getForm(): FormInterface;

    public function getData(): mixed;

    public function setData(mixed $data): self;

    public function getOption(string $name): mixed;

    public function getOptions(): array;

    public function getFormType(): string;

    public function onDefault(callable $callback): static;

    public function onInvalid(callable $callback): static;

    public function onSubmit(callable ...$callbacks): static;

    public function getResponse(): Response;

    // public function getToken();
    // public function followPropertyPath(array &$propertyPath): ?FormInterface;

    // public function reset();

    // public function addStep(callable $callback);
    // public function addConfirmStep();
    // public function getPreviousStep();
    // public function getStepMax();
    // public function getNextStep();
    // public function setStep(int $step);
    // public function getStep();
}
