<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\Coverage;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class SingleSubstFormat2 implements SubstitutionSubtable
{
    /** @var list<GlyphId> */
    public array $substituteGlyphIds;

    /** @param list<GlyphId> $substituteGlyphIds */
    public function __construct(public Coverage $coverage, array $substituteGlyphIds)
    {
        foreach ($substituteGlyphIds as $glyph) {
            if (!$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Every collection element must be a GlyphId object.');
            }
        }

        if (count($substituteGlyphIds) !== $coverage->count()) {
            throw new MalformedFontException('Single substitution and coverage counts differ.');
        }
        $this->substituteGlyphIds = array_values($substituteGlyphIds);
    }

    public function substitute(GlyphId $glyph): ?GlyphId
    {
        $index = $this->coverage->indexOf($glyph);

        return $index === null ? null : $this->substituteGlyphIds[$index];
    }

    public function lookupType(): int
    {
        return 1;
    }

    public function isSupported(): bool
    {
        return true;
    }
}
