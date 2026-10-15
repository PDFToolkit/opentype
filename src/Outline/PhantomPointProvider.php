<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Value\GlyphId;

interface PhantomPointProvider
{
    public function points(GlyphId $glyph, GlyphDescription $description): PhantomPoints;
}
