<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\Coverage;

final readonly class MultipleSubstFormat1 implements SubstitutionSubtable
{
    /** @var list<Sequence> */
    public array $sequences;

    /** @param list<Sequence> $sequences */
    public function __construct(public Coverage $coverage, array $sequences)
    {
        foreach ($sequences as $sequence) {
            if (!$sequence instanceof Sequence) {
                throw new \InvalidArgumentException('Every collection element must be a Sequence object.');
            }
        }

        if (count($sequences) !== $coverage->count()) {
            throw new MalformedFontException('Substitution set and coverage counts differ.');
        }
        $this->sequences = array_values($sequences);
    }

    public function lookupType(): int
    {
        return 2;
    }

    public function isSupported(): bool
    {
        return true;
    }
}
