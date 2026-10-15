<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class LinearThresholds
{
    /** @param list<int> $verticalPixels */
    public function __construct(public array $verticalPixels)
    {
        if (!array_is_list($verticalPixels) || $verticalPixels === [] || count($verticalPixels) > 65535) {
            throw new \InvalidArgumentException('LTSH requires one threshold per glyph.');
        }
        foreach ($verticalPixels as $value) {
            if (!is_int($value) || $value < 0 || $value > 255) {
                throw new \InvalidArgumentException('An LTSH threshold is uint8.');
            }
        }
    }
    public function threshold(GlyphId $glyph): int
    {
        return $this->verticalPixels[$glyph->value] ?? throw new \OutOfBoundsException('LTSH glyph is outside maxp.');
    }
    public function declaresLinear(GlyphId $glyph, int $pixelsPerEm): bool
    {
        if ($pixelsPerEm < 1) {
            throw new \InvalidArgumentException('A pixel size must be positive.');
        }
        return $pixelsPerEm >= $this->threshold($glyph);
    }
}
