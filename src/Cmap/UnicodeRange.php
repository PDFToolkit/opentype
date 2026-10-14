<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;

final readonly class UnicodeRange
{
    public function __construct(public CodePoint $startUnicodeValue, public int $additionalCount)
    {
        $end = $startUnicodeValue->value + $additionalCount;
        if ($additionalCount < 0 || $additionalCount > 255 || $end > 0x10FFFF
            || ($startUnicodeValue->value < 0xD800 && $end >= 0xD800)
        ) {
            throw new \InvalidArgumentException('A default UVS range requires consecutive Unicode scalar values and an unsigned byte count.');
        }
    }

    public function endUnicodeValue(): int
    {
        return $this->startUnicodeValue->value + $this->additionalCount;
    }

    public function contains(CodePoint $codePoint): bool
    {
        return $codePoint->value >= $this->startUnicodeValue->value && $codePoint->value <= $this->endUnicodeValue();
    }
}
