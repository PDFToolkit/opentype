<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Exception\UnsupportedFontException;

/** Explicit design-space units, pixel size, variation and hinted-point capabilities. */
final readonly class PositioningContext
{
    public function __construct(public int $unitsPerEm = 1000, public ?int $pixelsPerEm = null, public ?VariationDeltaResolver $variations = null, public ?AnchorPointResolver $outlinePoints = null, public bool $vertical = false)
    {
        if ($unitsPerEm < 16 || $unitsPerEm > 16384 || ($pixelsPerEm !== null && ($pixelsPerEm < 1 || $pixelsPerEm > 65535))) {
            throw new \InvalidArgumentException('Invalid positioning scale.');
        }
    }

    public function delta(DeviceAdjustment|VariationIndex|null $adjustment): float
    {
        if ($adjustment instanceof VariationIndex) {
            return ($this->variations ?? throw new UnsupportedFontException('A GPOS VariationIndex requires an explicit variation delta resolver.'))->delta($adjustment);
        }
        if ($adjustment === null || $this->pixelsPerEm === null) {
            return 0.0;
        }
        return $adjustment->delta($this->pixelsPerEm) * $this->unitsPerEm / $this->pixelsPerEm;
    }
}
