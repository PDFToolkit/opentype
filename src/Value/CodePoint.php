<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Value;

final readonly class CodePoint
{
    public function __construct(public int $value)
    {
        if ($value < 0 || $value > 0x10FFFF || ($value >= 0xD800 && $value <= 0xDFFF)) {
            throw new \InvalidArgumentException('A code point must be a Unicode scalar value.');
        }
    }
}
