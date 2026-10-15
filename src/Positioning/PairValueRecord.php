<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class PairValueRecord
{
    public function __construct(public GlyphId $secondGlyph, public ValueRecord $value1, public ValueRecord $value2)
    {
    }
}
