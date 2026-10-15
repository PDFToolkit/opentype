<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

/** Declared control-point bounds; these need not be the tight curve bounds. */
final readonly class GlyphBounds
{
    public function __construct(public int $xMin, public int $yMin, public int $xMax, public int $yMax)
    {
        if ($xMin > $xMax || $yMin > $yMax || min($xMin, $yMin) < -32768 || max($xMax, $yMax) > 32767) {
            throw new \InvalidArgumentException('Invalid TrueType glyph bounds.');
        }
    }
}
