<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Positioning\DeviceAdjustment;
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class CaretValue
{
    public function __construct(public ?int $coordinate = null, public ?int $caretValuePointIndex = null, public DeviceAdjustment|VariationIndex|null $adjustment = null)
    {
        if (($coordinate === null) === ($caretValuePointIndex === null) || ($coordinate !== null && ($coordinate < -32768 || $coordinate > 32767)) || ($caretValuePointIndex !== null && ($caretValuePointIndex < 0 || $caretValuePointIndex > 65535 || $adjustment !== null))) {
            throw new \InvalidArgumentException('A caret value requires either a signed coordinate or an outline point index.');
        }
    }

    public function resolve(GlyphId $glyph, PositioningContext $context): float
    {
        if ($this->caretValuePointIndex !== null) {
            $point = ($context->outlinePoints ?? throw new UnsupportedFontException('A point-index caret requires explicit outline point access.'))->point($glyph, $this->caretValuePointIndex);
            return $context->vertical ? $point->y : $point->x;
        }
        return ($this->coordinate ?? 0) + $context->delta($this->adjustment);
    }
}
