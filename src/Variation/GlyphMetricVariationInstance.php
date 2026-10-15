<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Value\GlyphId;

/** Explicit metric deltas for one normalized font instance, without outline access. */
final readonly class GlyphMetricVariationInstance
{
    private VariationInstance $instance;

    public function __construct(public GlyphMetricVariations $table, public NormalizedCoordinates $coordinates)
    {
        $this->instance = new VariationInstance($table->store, $coordinates);
    }

    public function advanceDelta(GlyphId $glyph): float
    {
        $this->checkGlyph($glyph);

        return $this->instance->delta($this->table->advances?->index($glyph->value) ?? new VariationIndex(0, $glyph->value));
    }

    public function firstBearingDelta(GlyphId $glyph): ?float
    {
        return $this->mapped($this->table->firstBearings, $glyph);
    }

    public function secondBearingDelta(GlyphId $glyph): ?float
    {
        return $this->mapped($this->table->secondBearings, $glyph);
    }

    public function verticalOriginDelta(GlyphId $glyph): ?float
    {
        return $this->mapped($this->table->verticalOrigins, $glyph);
    }

    private function mapped(?DeltaSetIndexMap $map, GlyphId $glyph): ?float
    {
        $this->checkGlyph($glyph);

        return $map === null ? null : $this->instance->delta($map->index($glyph->value));
    }

    private function checkGlyph(GlyphId $glyph): void
    {
        if ($glyph->value >= $this->table->glyphCount) {
            throw new \OutOfBoundsException('A metric glyph exceeds this font.');
        }
    }
}
