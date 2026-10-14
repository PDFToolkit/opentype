<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class Ligature
{
    /** @var list<GlyphId> */
    public array $componentGlyphIds;

    /** @param list<GlyphId> $componentGlyphIds The first component is supplied by Coverage. */
    public function __construct(public GlyphId $ligatureGlyph, array $componentGlyphIds)
    {
        foreach ($componentGlyphIds as $glyph) {
            if (!$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Every collection element must be a GlyphId object.');
            }
        }

        $this->componentGlyphIds = array_values($componentGlyphIds);
    }
}
