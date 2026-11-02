<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Contract\Validation;
use Omnibridge\Contract\Violation;
use Omnibridge\Validation\Constraint as Neutral;
use Omnibridge\Validation\Validator as PlainValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validation by symfony/validator: an object against what its class declares - Symfony's
 * attributes as ever, omnisense/omnibridge's kept constraints as attributes too - plus the
 * constraints given, Symfony's or the kept ones (SymfonyConstraints). Neutral\Unique goes to
 * omnibridge's validator with the application's $exists lookup, which Symfony's has no notion of;
 * its message said by the translator, when one is given (the validators domain), as Symfony's own.
 */
final class SymfonyValidation implements Validation
{
    /** @var (\Closure(string, array<string, mixed>, object|null): bool)|null */
    private readonly ?\Closure $exists;

    public function __construct(private readonly ValidatorInterface $validator, ?callable $exists = null, private readonly ?TranslatorInterface $translator = null)
    {
        $this->exists = null !== $exists ? $exists(...) : null;
    }

    /**
     * A message of omnibridge's validator in the page's language: its template translated alone (an
     * ICU message of omnibase's catalogues keeps its "{value}"), then {{ value }} replaced, and {value}
     * by the value as omnibase's own validators give it, unquoted.
     *
     * @param array<string, mixed> $parameters
     */
    private function translate(string $template, array $parameters): string
    {
        $count = $parameters['%count%'] ?? null;
        unset($parameters['%count%']);
        $forms = explode('|', null !== $this->translator ? $this->translator->trans($template, [], 'validators') : $template);
        $replacements = array_map(strval(...), $parameters);
        foreach ($parameters as $name => $value) {
            if (preg_match('/^\{\{ ?(\w+) ?\}\}$/', (string) $name, $m)) {
                $value = (string) $value;
                $replacements['{'.$m[1].'}'] ??= \strlen($value) >= 2 && '"' === $value[0] && '"' === $value[-1] ? substr($value, 1, -1) : $value;
            }
        }

        return strtr(1 === $count || 1 === \count($forms) ? $forms[0] : $forms[1], $replacements);
    }

    public function validate(mixed $value, array $constraints = [], ?array $groups = null): array
    {
        $unique = array_values(array_filter($constraints, static fn (object $constraint): bool => $constraint instanceof Neutral\Unique));
        $others = array_values(array_filter($constraints, static fn (object $constraint): bool => !$constraint instanceof Neutral\Unique));
        $record = \is_object($value) && !$value instanceof \DateTimeInterface && !$value instanceof \SplFileInfo;

        $violations = [];
        // Symfony validates a value by its class alone only for an object.
        if ($record || [] !== $others) {
            foreach ($this->validator->validate($value, [] !== $others ? SymfonyConstraints::translate($others) : null, $groups) as $violation) {
                $violations[] = new Violation($violation->getPropertyPath(), (string) $violation->getMessage(), $violation->getCode(), $violation->getParameters());
            }
        }

        $plain = new PlainValidator($this->exists, null !== $this->translator ? $this->translate(...) : null);
        if ($record) {
            array_push($violations, ...$this->declared($value, $groups), ...$plain->uniques($value, $groups));
        }
        array_push($violations, ...$plain->only($value, $unique, $groups));

        return $violations;
    }

    /**
     * The kept constraints an object's properties declare as attributes, which Symfony's metadata
     * does not read; Unique apart (Validator::uniques()).
     *
     * @param list<string>|null $groups
     *
     * @return list<Violation>
     */
    private function declared(object $record, ?array $groups): array
    {
        $violations = [];
        for ($current = new \ReflectionClass($record); false !== $current; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $current->getName()) {
                    continue;
                }
                foreach ($property->getAttributes(Neutral\Constraint::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $constraint = $attribute->newInstance();
                    if ($constraint instanceof Neutral\Unique) {
                        continue;
                    }
                    $value = $property->isInitialized($record) ? $property->getValue($record) : null;
                    foreach ($this->validate($value, [$constraint], $groups) as $violation) {
                        $path = '' === $violation->path ? $property->getName() : $property->getName().'.'.$violation->path;
                        $violations[] = new Violation($path, $violation->message, $violation->code, $violation->parameters);
                    }
                }
            }
        }

        return $violations;
    }
}
