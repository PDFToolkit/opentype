<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

use PDFToolkit\OpenType\Value\GlyphId;

/** Pixel widths already computed by the font producer; no scaler is invoked. */
final readonly class HorizontalDeviceRecord
{
    /** @param list<int> $widths */
    public function __construct(public int $pixelsPerEm, public int $maximumWidth, public array $widths)
    {
        if ($pixelsPerEm < 1 || $pixelsPerEm > 255 || $maximumWidth < 0 || $maximumWidth > 255 || !array_is_list($widths) || $widths === [] || count($widths) > 65535) {
            throw new \InvalidArgumentException('Invalid horizontal device record.');
        }
        foreach ($widths as $width) {
            if (!is_int($width) || $width < 0 || $width > $maximumWidth) {
                throw new \InvalidArgumentException('Device widths must fit their declared maximum.');
            }
        }
    }
    public function width(GlyphId $glyph): int
    {
        return $this->widths[$glyph->value] ?? throw new \OutOfBoundsException('Device width glyph is outside maxp.');
    }
}
