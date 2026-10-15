<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

use PDFToolkit\OpenType\Value\GlyphId;

/** VORG defaults and sparse glyph overrides for CFF/CFF2 fonts only. */
final readonly class VerticalOrigins
{
    /** @param array<int, int> $origins */
    public function __construct(public int $glyphCount, public int $defaultOrigin, public array $origins)
    {
        if ($glyphCount < 1 || $glyphCount > 65535 || $defaultOrigin < -32768 || $defaultOrigin > 32767) {
            throw new \InvalidArgumentException('Invalid vertical origin dimensions.');
        }
        foreach ($origins as $glyph => $origin) {
            if ($glyph < 0 || $glyph >= $glyphCount || $origin < -32768 || $origin > 32767) {
                throw new \InvalidArgumentException('A vertical origin record is outside its font or signed range.');
            }
        }
    }

    public function originFor(GlyphId $glyph): int
    {
        if ($glyph->value >= $this->glyphCount) {
            throw new \OutOfBoundsException('The origin glyph exceeds its font.');
        }

        return $this->origins[$glyph->value] ?? $this->defaultOrigin;
    }
}
