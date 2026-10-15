<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Substitution\GlyphSequence;

final readonly class PositionedGlyphSequence
{
    /** @param list<GlyphPosition> $positions */
    public function __construct(public GlyphSequence $sequence, public array $positions)
    {
        if (!array_is_list($positions) || count($positions) !== count($sequence->glyphs)) {
            throw new \InvalidArgumentException('Every glyph requires one explicit initial position.');
        }
        foreach ($positions as $position) {
            if (!$position instanceof GlyphPosition) {
                throw new \InvalidArgumentException('Positions must be GlyphPosition objects.');
            }
        }
    }
}
