<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\Coverage;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class SingleSubstFormat1 implements SubstitutionSubtable
{
    public function __construct(public Coverage $coverage, public int $deltaGlyphId)
    {
        if ($deltaGlyphId < -32768 || $deltaGlyphId > 32767) {
            throw new MalformedFontException('Single substitution delta must fit int16.');
        }
    }

    public function substitute(GlyphId $glyph): ?GlyphId
    {
        return $this->coverage->indexOf($glyph) === null ? null : new GlyphId(($glyph->value + $this->deltaGlyphId) & 0xFFFF);
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
