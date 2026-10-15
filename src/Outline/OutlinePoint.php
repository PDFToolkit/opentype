<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class OutlinePoint
{
    public function __construct(public float $x, public float $y, public bool $onCurve = true)
    {
        if (!is_finite($x) || !is_finite($y)) {
            throw new \InvalidArgumentException('Outline point coordinates must be finite.');
        }
    }
}
