<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

final readonly class HorizontalHeader
{
    public function __construct(public int $ascender, public int $descender, public int $lineGap, public int $advanceWidthMax, public int $minLeftSideBearing, public int $minRightSideBearing, public int $xMaxExtent, public int $caretSlopeRise, public int $caretSlopeRun, public int $caretOffset, public int $numberOfHMetrics)
    {
        if ($advanceWidthMax < 0 || $advanceWidthMax > 65535 || $numberOfHMetrics < 1 || $numberOfHMetrics > 65535) {
            throw new \InvalidArgumentException('Invalid horizontal header advance or metric count.');
        }
        foreach ([$ascender, $descender, $lineGap, $minLeftSideBearing, $minRightSideBearing, $xMaxExtent, $caretSlopeRise, $caretSlopeRun, $caretOffset] as $value) {
            if ($value < -32768 || $value > 32767) {
                throw new \InvalidArgumentException('A horizontal header field exceeds int16.');
            }
        }
    }
}
