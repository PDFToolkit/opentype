<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\GlyphId;

interface GlyphPredicate
{
    public function matches(GlyphId $glyph): bool;
}
