<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

/** Exact normalized coordinates stored as signed F2DOT14 integers. */
final readonly class AxisValueMap
{
    public function __construct(public int $fromCoordinate, public int $toCoordinate)
    {
        if ($fromCoordinate < -16384 || $fromCoordinate > 16384 || $toCoordinate < -16384 || $toCoordinate > 16384) {
            throw new \InvalidArgumentException('Axis segment coordinates must be in normalized F2DOT14 range.');
        }
    }
}
