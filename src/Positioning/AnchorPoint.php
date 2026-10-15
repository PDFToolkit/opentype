<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class AnchorPoint
{
    public function __construct(public float $x, public float $y)
    {
        if (!is_finite($x) || !is_finite($y)) {
            throw new \InvalidArgumentException('Anchor coordinates must be finite.');
        }
    }
}
