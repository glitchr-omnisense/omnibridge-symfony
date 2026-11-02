<?php

namespace Omnibridge\Symfony\Tests;

use Omnibridge\Contract\Violation;
use Omnibridge\Symfony\Contract\SymfonyConstraints;
use Omnibridge\Symfony\Contract\SymfonyValidation;
use Omnibridge\Validation\Constraint as Neutral;
use Omnibridge\Validation\Validator as PlainValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

final class ValidationTest extends TestCase
{
    private function validation(?callable $exists = null): SymfonyValidation
    {
        return new SymfonyValidation(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(), $exists);
    }

    /** @param list<Violation> $violations @return list<string> */
    private static function messages(array $violations): array
    {
        return array_map(static fn (Violation $v): string => ('' === $v->path ? '' : $v->path.': ').$v->message, $violations);
    }

    public function testEachKeptConstraintGivesTheMessageOfThePlainValidator(): void
    {
        $cases = [
            ['', [new Neutral\NotBlank()]],
            ['ab', [new Neutral\Length(min: 3)]],
            ['ab', [new Neutral\Length(max: 1)]],
            ['ringo@', [new Neutral\Email()]],
            ['ab1', [new Neutral\Regex('/^[a-z]+$/')]],
            ['x', [new Neutral\Choice(['a', 'b'])]],
            [[], [new Neutral\Choice(['a', 'b'], multiple: true, min: 1)]],
            [6, [new Neutral\Range(min: 1, max: 5)]],
            [0, [new Neutral\Range(min: 1)]],
            ['ringo@example.com', [new Neutral\Email()]],
        ];
        foreach ($cases as [$value, $constraints]) {
            self::assertSame(self::messages((new PlainValidator())->validate($value, $constraints)), self::messages($this->validation()->validate($value, $constraints)));
        }
    }

    public function testAnObjectBySymfonysAttributesAndTheKeptOnesAndUnique(): void
    {
        $record = new Member();
        $record->email = 'nope';
        $record->name = '';
        $record->login = 'ringo';

        self::assertSame([
            'email: This value is not a valid email address.',
            'name: This value should not be blank.',
            'login: This value is already used.',
        ], self::messages($this->validation(static fn (string $class, array $criteria): bool => ['login' => 'ringo'] === $criteria)->validate($record)));
    }

    public function testAUniquesMessageIsSaidByTheTranslatorItsValueTheIcuArgumentToo(): void
    {
        $translator = new \Symfony\Component\Translation\Translator('fr');
        $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
        // omnibase's catalogue is ICU's: without its arguments the message keeps its "{value}".
        $translator->addResource('array', ['unique_entity' => '"{value}" est déjà utilisé.'], 'fr', 'validators');
        $validation = new SymfonyValidation(Validation::createValidator(), static fn (): bool => true, $translator);

        self::assertSame(['login: "Marki" est déjà utilisé.'], self::messages($validation->validate(['login' => 'Marki'], [new Neutral\Unique(['login'], Member::class, message: 'unique_entity')])));
        self::assertSame(['login: This value is already used.'], self::messages($this->validation(static fn (): bool => true)->validate(['login' => 'Marki'], [new Neutral\Unique(['login'], Member::class)])), 'without a translator, as before');
    }

    public function testConstraintsTranslateToSymfonysAndSymfonysPassAsTheyAre(): void
    {
        $own = new Assert\Url();

        self::assertInstanceOf(Assert\Length::class, SymfonyConstraints::one(new Neutral\Length(max: 2)));
        self::assertSame($own, SymfonyConstraints::one($own));

        $this->expectException(\InvalidArgumentException::class);
        SymfonyConstraints::one(new Neutral\Unique());
    }
}

final class Member
{
    #[Assert\Email]
    public ?string $email = null;

    #[Neutral\NotBlank]
    public ?string $name = null;

    #[Neutral\Unique]
    public ?string $login = null;
}
