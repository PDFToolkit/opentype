<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

/** Integer bounds also permit a last-resort format 13 range spanning surrogate values. */
final readonly class CharacterMapGroup
{
    public function __construct(public int $start, public int $end, public GlyphId $glyph)
    {
        if ($start < 0 || $start > $end || $end > 0x10FFFF) {
            throw new \InvalidArgumentException('A cmap group must be an ordered Unicode code range.');
        }
    }
}
