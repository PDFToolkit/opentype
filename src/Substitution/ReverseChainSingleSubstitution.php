<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Layout\ContextRule;
use PDFToolkit\OpenType\Layout\Coverage;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class ReverseChainSingleSubstitution implements SubstitutionSubtable
{
    /** @param list<GlyphId> $substitutes */
    public function __construct(public Coverage $coverage, public ContextRule $context, public array $substitutes)
    {
        if ($coverage->count() !== count($substitutes) || count($context->input) !== 1 || $context->lookupRecords !== []) {
            throw new \InvalidArgumentException('Reverse substitutions require one substitute per covered glyph and a single context input.');
        }
    }

    public function lookupType(): int
    {
        return 8;
    }

    public function isSupported(): bool
    {
        return true;
    }
}
