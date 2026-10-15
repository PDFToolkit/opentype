<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

/** Deprecated Type 2 endchar composite, whose codes always use StandardEncoding. */
final readonly class CffComposite
{
    public function __construct(public float $dx, public float $dy, public int $baseCode, public int $accentCode)
    {
        if (!is_finite($dx) || !is_finite($dy) || $baseCode < 0 || $baseCode > 255 || $accentCode < 0 || $accentCode > 255) {
            throw new \InvalidArgumentException('Invalid Type 2 composite operands.');
        }
    }
}
