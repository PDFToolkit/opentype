<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class AlternateSet
{
    /** @var list<GlyphId> */
    public array $alternateGlyphIds;

    /** @param list<GlyphId> $alternateGlyphIds */
    public function __construct(array $alternateGlyphIds)
    {
        foreach ($alternateGlyphIds as $glyph) {
            if (!$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Every collection element must be a GlyphId object.');
            }
        }

        $this->alternateGlyphIds = array_values($alternateGlyphIds);
    }
}
