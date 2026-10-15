<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

/** Unscaled CVT design values; fractional values can result from cvar interpolation. */
final readonly class ControlValues
{
    /** @param list<float> $values */
    public function __construct(public array $values)
    {
        if (!array_is_list($values) || count($values) > 65536) {
            throw new \InvalidArgumentException('Control values require a bounded ordered list.');
        }
        foreach ($values as $value) {
            if (!is_finite($value)) {
                throw new \InvalidArgumentException('Control values must be finite.');
            }
        }
    }
}
