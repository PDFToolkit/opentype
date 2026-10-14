<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Value;

final readonly class Tag
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[\\x21-\\x7E]{1,4} *$/D', $value) !== 1 || strlen($value) !== 4) {
            throw new \InvalidArgumentException('An OpenType tag contains one to four printable non-space ASCII bytes, padded with trailing spaces.');
        }
    }
}
