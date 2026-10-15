<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class GvarTable
{
    /** @param list<list<TupleVariation>> $glyphVariations */
    public function __construct(public int $axisCount, public array $glyphVariations)
    {
        if ($axisCount < 1 || $axisCount > 65535 || !array_is_list($glyphVariations) || count($glyphVariations) > 65535) {
            throw new \InvalidArgumentException('Invalid gvar dimensions.');
        }
        foreach ($glyphVariations as $variations) {
            if (!array_is_list($variations)) {
                throw new \InvalidArgumentException('A gvar glyph requires an ordered tuple list.');
            }
            foreach ($variations as $variation) {
                if (!$variation instanceof TupleVariation || count($variation->region->peak->values) !== $axisCount) {
                    throw new \InvalidArgumentException('gvar region dimensions must match.');
                }
            }
        }
    }

    /** @return list<TupleVariation> */
    public function glyph(GlyphId $glyph): array
    {
        return $this->glyphVariations[$glyph->value] ?? throw new \OutOfBoundsException('A glyph exceeds its gvar table.');
    }
}
