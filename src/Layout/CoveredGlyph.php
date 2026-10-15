<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class CoveredGlyph implements GlyphPredicate
{
    public function __construct(public Coverage $coverage)
    {
    }

    public function matches(GlyphId $glyph): bool
    {
        return $this->coverage->indexOf($glyph) !== null;
    }
}
