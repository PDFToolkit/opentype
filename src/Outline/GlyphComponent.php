<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Value\GlyphId;

/** Arguments are signed offsets or unsigned parent/child point numbers, as selected by flags. */
final readonly class GlyphComponent
{
    public function __construct(public GlyphId $glyph, public int $flags, public int $argument1, public int $argument2, public ComponentTransform $transform = new ComponentTransform())
    {
        if ($flags < 0 || $flags > 0x1FFF || ($flags & 0x10) !== 0 || ($flags & 0x1800) === 0x1800
            || ($this->usesOffsets() ? min($argument1, $argument2) < -32768 || max($argument1, $argument2) > 32767 : min($argument1, $argument2) < 0 || max($argument1, $argument2) > 65535)
        ) {
            throw new \InvalidArgumentException('Invalid TrueType component flags or arguments.');
        }
    }

    public function usesOffsets(): bool
    {
        return ($this->flags & 2) !== 0;
    }

    public function useMyMetrics(): bool
    {
        return ($this->flags & 0x0200) !== 0;
    }
}
