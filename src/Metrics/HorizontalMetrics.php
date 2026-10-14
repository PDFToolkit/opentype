<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class HorizontalMetrics
{
    /** @param non-empty-list<LongHorMetric> $metrics */
    public function __construct(public int $unitsPerEm, private array $metrics)
    {
        if ($unitsPerEm < 16 || $unitsPerEm > 16384 || $metrics === [] || !array_is_list($metrics)) {
            throw new \InvalidArgumentException('Horizontal metrics require valid unitsPerEm and glyph-indexed metric records.');
        }
        foreach ($metrics as $metric) {
            if (!$metric instanceof LongHorMetric) {
                throw new \InvalidArgumentException('Horizontal metrics contain immutable LongHorMetric records.');
            }
        }
    }

    public function metricFor(GlyphId $glyph): LongHorMetric
    {
        return $this->metrics[$glyph->value] ?? throw new \OutOfBoundsException('The glyph is outside this font.');
    }

    public function advanceWidth(GlyphId $glyph): int
    {
        return $this->metricFor($glyph)->advanceWidth;
    }

    public function leftSideBearing(GlyphId $glyph): int
    {
        return $this->metricFor($glyph)->leftSideBearing;
    }

    public function glyphCount(): int
    {
        return count($this->metrics);
    }
}
