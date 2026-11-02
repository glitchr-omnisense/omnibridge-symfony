<?php

namespace Omnibridge\Symfony\Contract;

use Omnibridge\Validation\Constraint as Neutral;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * omnisense/omnibridge's kept constraints as symfony/validator's own, with their messages and
 * groups: what a form or SymfonyValidation hands the validator. Symfony's constraints and a host's
 * own pass as they are. Neutral\Unique has no equivalent without Doctrine's bridge:
 * SymfonyValidation checks it, through Omnibridge\Validation\Validator; on a form field it is
 * refused aloud.
 */
final class SymfonyConstraints
{
    private function __construct()
    {
    }

    /**
     * @param list<object> $constraints
     *
     * @return list<object>
     */
    public static function translate(array $constraints): array
    {
        return array_map(self::one(...), $constraints);
    }

    public static function one(object $constraint): object
    {
        if ($constraint instanceof Constraint || !$constraint instanceof Neutral\Constraint) {
            return $constraint;
        }

        $groups = $constraint->groups;

        return match (true) {
            $constraint instanceof Neutral\NotBlank => new Assert\NotBlank(message: $constraint->message, allowNull: $constraint->allowNull, groups: $groups),
            $constraint instanceof Neutral\Length => new Assert\Length(min: $constraint->min, max: $constraint->max, minMessage: $constraint->minMessage, maxMessage: $constraint->maxMessage, groups: $groups),
            $constraint instanceof Neutral\Email => new Assert\Email(message: $constraint->message, mode: Assert\Email::VALIDATION_MODE_HTML5, groups: $groups),
            $constraint instanceof Neutral\Count => new Assert\Count(min: $constraint->min, max: $constraint->max, minMessage: $constraint->minMessage, maxMessage: $constraint->maxMessage, groups: $groups),
            $constraint instanceof Neutral\Regex => new Assert\Regex(pattern: $constraint->pattern, match: $constraint->match, message: $constraint->message, groups: $groups),
            $constraint instanceof Neutral\Choice => new Assert\Choice(choices: $constraint->choices, multiple: $constraint->multiple, min: $constraint->min, max: $constraint->max, message: $constraint->message, multipleMessage: $constraint->multipleMessage, minMessage: $constraint->minMessage, maxMessage: $constraint->maxMessage, groups: $groups),
            $constraint instanceof Neutral\Range => self::range($constraint),
            $constraint instanceof Neutral\File => new Assert\File(maxSize: $constraint->maxSize, mimeTypes: [] !== $constraint->mimeTypes ? $constraint->mimeTypes : null, maxSizeMessage: $constraint->maxSizeMessage, mimeTypesMessage: $constraint->mimeTypesMessage, notFoundMessage: $constraint->notFoundMessage, groups: $groups),
            default => throw new \InvalidArgumentException(sprintf('%s has no symfony/validator equivalent here.', $constraint::class)),
        };
    }

    private static function range(Neutral\Range $constraint): Assert\Range
    {
        // Symfony refuses a min or max message with both limits: its notInRangeMessage is that case's.
        $both = null !== $constraint->min && null !== $constraint->max;

        return new Assert\Range(
            notInRangeMessage: $both ? $constraint->notInRangeMessage : null,
            minMessage: $both ? null : $constraint->minMessage,
            maxMessage: $both ? null : $constraint->maxMessage,
            invalidMessage: $constraint->invalidMessage,
            min: $constraint->min,
            max: $constraint->max,
            groups: $constraint->groups,
        );
    }
}
