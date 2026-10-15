<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class VariationIndex
{
    public function __construct(public int $outerIndex, public int $innerIndex)
    {
        if ($outerIndex < 0 || $outerIndex > 65535 || $innerIndex < 0 || $innerIndex > 65535) {
            throw new \InvalidArgumentException('Variation indices are unsigned 16-bit values.');
        }
    }
}
