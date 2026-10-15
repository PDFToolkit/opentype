<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class ValueRecord
{
    public function __construct(public int $xPlacement = 0, public int $yPlacement = 0, public int $xAdvance = 0, public int $yAdvance = 0, public DeviceAdjustment|VariationIndex|null $xPlacementAdjustment = null, public DeviceAdjustment|VariationIndex|null $yPlacementAdjustment = null, public DeviceAdjustment|VariationIndex|null $xAdvanceAdjustment = null, public DeviceAdjustment|VariationIndex|null $yAdvanceAdjustment = null)
    {
        foreach ([$xPlacement, $yPlacement, $xAdvance, $yAdvance] as $value) {
            if ($value < -32768 || $value > 32767) {
                throw new \InvalidArgumentException('A GPOS value must fit a signed 16-bit design coordinate.');
            }
        }
    }
}
