<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\Coverage;

final readonly class LigatureSubstFormat1 implements SubstitutionSubtable
{
    /** @var list<LigatureSet> */
    public array $ligatureSets;

    /** @param list<LigatureSet> $ligatureSets */
    public function __construct(public Coverage $coverage, array $ligatureSets)
    {
        foreach ($ligatureSets as $set) {
            if (!$set instanceof LigatureSet) {
                throw new \InvalidArgumentException('Every collection element must be a LigatureSet object.');
            }
        }

        if (count($ligatureSets) !== $coverage->count()) {
            throw new MalformedFontException('Substitution set and coverage counts differ.');
        }
        $this->ligatureSets = array_values($ligatureSets);
    }

    public function lookupType(): int
    {
        return 4;
    }

    public function isSupported(): bool
    {
        return true;
    }
}
