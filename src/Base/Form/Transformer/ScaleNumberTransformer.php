<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Base\Form\Transformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a number type and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<int|float, string>
 */
class ScaleNumberTransformer implements DataTransformerInterface
{
    protected float $divisor;

    public function __construct(float $divisor)
    {
        $this->divisor = $divisor;
    }

    /**
     * Transforms a localized number into an integer or float.
     *
     * @param string $value The localized value
     *
     * @throws TransformationFailedException if the given value is not a string
     *                                       or if the value cannot be transformed
     */
    public function transform(mixed $value): float|int|null
    {
        if (null !== $value && 1 !== $this->divisor) {
            if (!is_numeric($value)) {
                throw new TransformationFailedException('Expected a numeric.');
            }
            $value /= $this->divisor;
        }

        return $value;
    }

    /**
     * Transforms a localized money string into a normalized format.
     *
     * @param string $value Localized money string
     *
     * @throws TransformationFailedException if the given value is not a string
     *                                       or if the value cannot be transformed
     */
    public function reverseTransform(mixed $value): int|float|null
    {
        if (null !== $value && 1 !== $this->divisor) {
            $value = (float) (string) ($value * $this->divisor);
        }

        return $value;
    }
}
