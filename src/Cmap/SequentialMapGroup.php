<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class SequentialMapGroup
{
    public function __construct(public CodePoint $startCharCode, public CodePoint $endCharCode, public GlyphId $startGlyphId)
    {
        if ($startCharCode->value > $endCharCode->value
            || ($startCharCode->value < 0xD800 && $endCharCode->value >= 0xD800)
            || $startGlyphId->value + $endCharCode->value - $startCharCode->value > 65535
        ) {
            throw new \InvalidArgumentException('A sequential cmap group must cover ordered Unicode scalars and bounded consecutive glyph IDs.');
        }
    }
}
