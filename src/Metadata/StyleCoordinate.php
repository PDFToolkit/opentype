<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

final readonly class StyleCoordinate
{
    public function __construct(public int $axisIndex, public float $value)
    {
        if ($axisIndex < 0 || $axisIndex > 65535 || !is_finite($value) || $value < -32768 || $value >= 32768) {
            throw new \InvalidArgumentException('STAT coordinates require uint16 axis and Fixed user values.');
        }
    }
}
