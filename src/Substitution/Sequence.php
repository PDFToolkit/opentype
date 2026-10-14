<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class Sequence
{
    /** @var non-empty-list<GlyphId> */
    public array $substituteGlyphIds;

    /** @param non-empty-list<GlyphId> $substituteGlyphIds */
    public function __construct(array $substituteGlyphIds)
    {
        foreach ($substituteGlyphIds as $glyph) {
            if (!$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Every collection element must be a GlyphId object.');
            }
        }

        if ($substituteGlyphIds === []) {
            throw new MalformedFontException('A substitution sequence or alternate set cannot be empty.');
        }
        $this->substituteGlyphIds = array_values($substituteGlyphIds);
    }
}
