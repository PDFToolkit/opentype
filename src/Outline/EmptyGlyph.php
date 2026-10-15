<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

/** Equal consecutive loca offsets, with no glyph data. */
final readonly class EmptyGlyph implements GlyphDescription
{
    public function instructions(): string
    {
        return '';
    }
}
