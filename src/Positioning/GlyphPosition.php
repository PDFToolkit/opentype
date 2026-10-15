<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class GlyphPosition
{
    public function __construct(public float $xAdvance = 0, public float $yAdvance = 0, public float $xOffset = 0, public float $yOffset = 0)
    {
        foreach ([$xAdvance, $yAdvance, $xOffset, $yOffset] as $value) {
            if (!is_finite($value)) {
                throw new \InvalidArgumentException('Positioned glyph coordinates must be finite.');
            }
        }
    }

    public function adjusted(ValueRecord $value, PositioningContext $context): self
    {
        return new self($this->xAdvance + ($context->vertical ? 0 : $value->xAdvance + $context->delta($value->xAdvanceAdjustment)), $this->yAdvance + ($context->vertical ? $value->yAdvance + $context->delta($value->yAdvanceAdjustment) : 0), $this->xOffset + $value->xPlacement + $context->delta($value->xPlacementAdjustment), $this->yOffset + $value->yPlacement + $context->delta($value->yPlacementAdjustment));
    }
}
