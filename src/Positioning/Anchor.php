<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class Anchor
{
    public function __construct(public int $xCoordinate, public int $yCoordinate, public ?int $anchorPoint = null, public DeviceAdjustment|VariationIndex|null $xAdjustment = null, public DeviceAdjustment|VariationIndex|null $yAdjustment = null)
    {
        if ($xCoordinate < -32768 || $xCoordinate > 32767 || $yCoordinate < -32768 || $yCoordinate > 32767 || ($anchorPoint !== null && ($anchorPoint < 0 || $anchorPoint > 65535))) {
            throw new \InvalidArgumentException('Invalid GPOS anchor.');
        }
    }

    public function resolve(GlyphId $glyph, PositioningContext $context): AnchorPoint
    {
        if ($this->anchorPoint !== null && $context->outlinePoints !== null) {
            return $context->outlinePoints->point($glyph, $this->anchorPoint);
        }
        return new AnchorPoint($this->xCoordinate + $context->delta($this->xAdjustment), $this->yCoordinate + $context->delta($this->yAdjustment));
    }
}
