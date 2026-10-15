<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

final readonly class LongVerMetric
{
    public function __construct(public int $advanceHeight, public int $topSideBearing)
    {
        if ($advanceHeight < 0 || $advanceHeight > 65535 || $topSideBearing < -32768 || $topSideBearing > 32767) {
            throw new \InvalidArgumentException('Vertical metrics must fit UFWORD and FWORD.');
        }
    }
}
