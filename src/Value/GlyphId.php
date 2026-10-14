<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Value;

final readonly class GlyphId
{
    public function __construct(public int $value)
    {
        if ($value < 0 || $value > 0xFFFF) {
            throw new \InvalidArgumentException('A glyph ID must be an unsigned 16-bit integer.');
        }
    }
}
