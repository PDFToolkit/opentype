<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Value\GlyphId;

/** Legacy one-byte CFF encoding; Unicode selection remains cmap's responsibility. */
final readonly class CffEncoding
{
    /** @param array<int, GlyphId> $codes */
    public function __construct(public array $codes)
    {
        foreach ($codes as $code => $glyph) {
            if ($code < 0 || $code > 255 || !$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Invalid CFF encoding map.');
            }
        }
    }

    public function glyph(int $code): ?GlyphId
    {
        return $this->codes[$code] ?? null;
    }
}
