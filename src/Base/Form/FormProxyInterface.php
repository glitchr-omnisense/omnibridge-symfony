<?php

namespace Base\Form;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;

interface FormProxyInterface
{
    public function all();

    public function add(string $name, ?FormInterface $form): self;

    public function remove(string $name): self;

    public function has(string $name): bool;

    public function get(string $name): ?FormInterface;

    public function getProcessor(string $name): ?FormProcessorInterface;

    public function setData(string $name, mixed $data): self;

    public function getData(string $name, ?string $childName): mixed;

    public function create(string $name, string $type = FormType::class, mixed $data = null, array $options = []): ?FormInterface;

    public function createProcessor(string $name, string $formTypeClass = FormType::class, array $options = []): ?FormProcessorInterface;

    public function submit(string $name, string|array|null $submittedData, bool $clearMissing = true): ?FormInterface;
}
