<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class CoverageFormat1 implements Coverage
{
    /** @var list<GlyphId> */
    public array $glyphArray;

    /** @param list<GlyphId> $glyphArray */
    public function __construct(array $glyphArray)
    {
        foreach ($glyphArray as $glyph) {
            if (!$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Every collection element must be a GlyphId object.');
            }
        }

        $previous = -1;
        foreach ($glyphArray as $glyph) {
            if ($glyph->value <= $previous) {
                throw new MalformedFontException('Coverage glyphs must be strictly increasing.');
            }
            $previous = $glyph->value;
        }
        $this->glyphArray = array_values($glyphArray);
    }

    public function indexOf(GlyphId $glyph): ?int
    {
        $low = 0;
        $high = count($this->glyphArray) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $value = $this->glyphArray[$middle]->value;
            if ($value === $glyph->value) {
                return $middle;
            }
            if ($value < $glyph->value) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->glyphArray);
    }

    public function glyphs(): array
    {
        return $this->glyphArray;
    }
}
