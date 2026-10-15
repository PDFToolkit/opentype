<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Metrics\HorizontalMetrics;
use PDFToolkit\OpenType\Metrics\VerticalMetrics;
use PDFToolkit\OpenType\Value\GlyphId;

/** Explicit default-instance metrics; HVAR/VVAR deltas must not be applied here again. */
final readonly class MetricPhantomProvider implements PhantomPointProvider
{
    public function __construct(private HorizontalMetrics $horizontal, private ?VerticalMetrics $vertical = null)
    {
        if ($vertical !== null && ($horizontal->glyphCount() !== $vertical->glyphCount() || $horizontal->unitsPerEm !== $vertical->unitsPerEm)) {
            throw new \InvalidArgumentException('Phantom metrics must describe the same glyph space.');
        }
    }

    public function points(GlyphId $glyph, GlyphDescription $description): PhantomPoints
    {
        $bounds = $description instanceof SimpleGlyph || $description instanceof CompositeGlyph ? $description->bounds : new GlyphBounds(0, 0, 0, 0);
        $left = $bounds->xMin - $this->horizontal->leftSideBearing($glyph);
        $top = $this->vertical === null ? 0 : $bounds->yMax + $this->vertical->topSideBearing($glyph);

        return new PhantomPoints(new OutlinePoint($left, 0), new OutlinePoint($left + $this->horizontal->advanceWidth($glyph), 0), new OutlinePoint(0, $top), new OutlinePoint(0, $top - ($this->vertical?->advanceHeight($glyph) ?? 0)));
    }
}
