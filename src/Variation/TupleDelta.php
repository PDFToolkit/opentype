<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class TupleDelta
{
    public function __construct(public float $x, public float $y = 0.0)
    {
        if (!is_finite($x) || !is_finite($y)) {
            throw new \InvalidArgumentException('Tuple deltas must be finite.');
        }
    }
}
