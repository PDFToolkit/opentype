<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

/** All vhea 1.0/1.1 fields; ascent/descent/lineGap retain their versioned definitions. */
final readonly class VerticalHeader
{
    public function __construct(
        public int $version,
        public int $ascent,
        public int $descent,
        public int $lineGap,
        public int $advanceHeightMax,
        public int $minTopSideBearing,
        public int $minBottomSideBearing,
        public int $yMaxExtent,
        public int $caretSlopeRise,
        public int $caretSlopeRun,
        public int $caretOffset,
        public int $numOfLongVerMetrics,
    ) {
        if (!in_array($version, [0x00010000, 0x00011000], true) || $advanceHeightMax < 0 || $advanceHeightMax > 65535 || $numOfLongVerMetrics < 1 || $numOfLongVerMetrics > 65535 || ($version === 0x00010000 && $lineGap !== 0)) {
            throw new \InvalidArgumentException('Invalid vhea version, advance, count or reserved line gap.');
        }
        foreach ([$ascent, $descent, $lineGap, $minTopSideBearing, $minBottomSideBearing, $yMaxExtent, $caretSlopeRise, $caretSlopeRun, $caretOffset] as $value) {
            if ($value < -32768 || $value > 32767) {
                throw new \InvalidArgumentException('A vertical header field exceeds FWORD.');
            }
        }
    }
}
