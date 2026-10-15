<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class VerticalMetrics
{
    /** @param non-empty-list<LongVerMetric> $metrics */
    public function __construct(public int $unitsPerEm, public VerticalHeader $header, private array $metrics)
    {
        if ($unitsPerEm < 16 || $unitsPerEm > 16384 || $metrics === [] || !array_is_list($metrics) || $header->numOfLongVerMetrics > count($metrics)) {
            throw new \InvalidArgumentException('Vertical metrics require valid unitsPerEm and indexed glyph metrics.');
        }
        foreach ($metrics as $metric) {
            if (!$metric instanceof LongVerMetric) {
                throw new \InvalidArgumentException('Vertical metrics contain immutable LongVerMetric records.');
            }
        }
    }

    public function metricFor(GlyphId $glyph): LongVerMetric
    {
        return $this->metrics[$glyph->value] ?? throw new \OutOfBoundsException('The glyph is outside this font.');
    }

    public function advanceHeight(GlyphId $glyph): int
    {
        return $this->metricFor($glyph)->advanceHeight;
    }

    public function topSideBearing(GlyphId $glyph): int
    {
        return $this->metricFor($glyph)->topSideBearing;
    }

    public function glyphCount(): int
    {
        return count($this->metrics);
    }
}
