<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class TrueTypeOutlines
{
    /** @param list<GlyphDescription> $glyphs */
    public function __construct(public array $glyphs)
    {
        if ($glyphs === [] || !array_is_list($glyphs) || count($glyphs) > 65535) {
            throw new \InvalidArgumentException('TrueType outlines require a bounded glyph list.');
        }
        foreach ($glyphs as $glyph) {
            if (!$glyph instanceof GlyphDescription) {
                throw new \InvalidArgumentException('Outline tables contain immutable glyph descriptions.');
            }
            if ($glyph instanceof CompositeGlyph) {
                foreach ($glyph->components as $component) {
                    if ($component->glyph->value >= count($glyphs)) {
                        throw new \InvalidArgumentException('A composite references a glyph outside this font.');
                    }
                }
            }
        }
    }

    public function glyph(GlyphId $glyph): GlyphDescription
    {
        return $this->glyphs[$glyph->value] ?? throw new \OutOfBoundsException('A glyph exceeds this outline font.');
    }
}
