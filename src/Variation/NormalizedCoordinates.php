<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

/** Explicit normalized axis coordinates in fvar axis order; no hidden default instance. */
final readonly class NormalizedCoordinates
{
    /** @param list<float> $values */
    public function __construct(public array $values)
    {
        if (!array_is_list($values)) {
            throw new \InvalidArgumentException('Variation coordinates must be in axis order.');
        }
        foreach ($values as $value) {
            if ((!is_float($value) && !is_int($value)) || !is_finite($value) || $value < -1 || $value > 1) {
                throw new \InvalidArgumentException('Normalized variation coordinates must lie in [-1, 1].');
            }
        }
    }

    public function axis(int $index): float
    {
        return $this->values[$index] ?? throw new \OutOfBoundsException('An explicit coordinate is required for each referenced variation axis.');
    }
}
