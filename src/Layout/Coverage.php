<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\GlyphId;

interface Coverage
{
    public function indexOf(GlyphId $glyph): ?int;

    public function count(): int;

    /** @return list<GlyphId> */
    public function glyphs(): array;
}
