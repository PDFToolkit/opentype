<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class RangeRecord
{
    public function __construct(public GlyphId $startGlyphId, public GlyphId $endGlyphId, public int $startCoverageIndex)
    {
        if ($startGlyphId->value > $endGlyphId->value || $startCoverageIndex < 0 || $startCoverageIndex > 65535) {
            throw new MalformedFontException('Invalid coverage range.');
        }
    }

    public function count(): int
    {
        return $this->endGlyphId->value - $this->startGlyphId->value + 1;
    }
}
