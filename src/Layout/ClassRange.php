<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class ClassRange
{
    public function __construct(public int $startGlyph, public int $endGlyph, public int $classValue)
    {
        if ($startGlyph < 0 || $endGlyph < $startGlyph || $endGlyph > 65535 || $classValue < 0 || $classValue > 65535) {
            throw new MalformedFontException('Invalid glyph class range.');
        }
    }
}
