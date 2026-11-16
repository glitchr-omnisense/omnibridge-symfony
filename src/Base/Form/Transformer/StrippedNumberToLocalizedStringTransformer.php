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
use Symfony\Component\Form\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;

/**
 * Transforms between a number type and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<int|float, string>
 */
class StrippedNumberToLocalizedStringTransformer extends NumberToLocalizedStringTransformer
{
    protected $prefix;
    protected $suffix;

    /**
     * @param $prefix
     * @param $suffix
     * @param ...$args
     */
    public function __construct($prefix, $suffix, ...$args)
    {
        parent::__construct(...$args);
        $this->prefix = $prefix;
        $this->suffix = $suffix;
    }

    /**
     * Transforms a localized number into an integer or float.
     *
     * @param string $value The localized value
     *
     * @throws TransformationFailedException if the given value is not a string
     *                                       or if the value cannot be transformed
     */
    public function reverseTransform(mixed $value): int|float|null
    {
        $value = $this->prefix ? str_lstrip($value, $this->prefix) : $value;
        $value = $this->suffix ? str_rstrip($value, $this->suffix) : $value;

        return parent::reverseTransform($value);
    }
}
