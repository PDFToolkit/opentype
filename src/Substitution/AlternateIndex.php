<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class AlternateIndex implements AlternateSelector
{
    public function __construct(public int $oneBasedIndex)
    {
        if ($oneBasedIndex < 1 || $oneBasedIndex > 65535) {
            throw new \InvalidArgumentException('An alternate index must be between 1 and 65535.');
        }
    }

    public function select(GlyphId $input, AlternateSet $alternates): GlyphId
    {
        return $alternates->alternateGlyphIds[$this->oneBasedIndex - 1]
            ?? throw new UnsupportedFontException('The requested alternate is not defined for this glyph.');
    }
}
