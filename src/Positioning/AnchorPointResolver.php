<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Value\GlyphId;

/** Optional hinted outline point access, supplied by the application. */
interface AnchorPointResolver
{
    public function point(GlyphId $glyph, int $pointIndex): AnchorPoint;
}
