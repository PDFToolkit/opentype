<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\GlyphId;

/** @internal One successful action in a lookup pass. */
final readonly class SubstitutionMatch
{
    /** @param non-empty-list<GlyphId> $replacement */
    public function __construct(public int $consumed, public array $replacement)
    {
    }
}
