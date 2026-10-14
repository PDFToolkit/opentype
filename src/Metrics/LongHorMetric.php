<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

final readonly class LongHorMetric
{
    public function __construct(public int $advanceWidth, public int $leftSideBearing)
    {
        if ($advanceWidth < 0 || $advanceWidth > 65535 || $leftSideBearing < -32768 || $leftSideBearing > 32767) {
            throw new \InvalidArgumentException('Horizontal metric fields must fit UFWORD and FWORD.');
        }
    }
}
