<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\Coverage;

final readonly class AlternateSubstFormat1 implements SubstitutionSubtable
{
    /** @var list<AlternateSet> */
    public array $alternateSets;

    /** @param list<AlternateSet> $alternateSets */
    public function __construct(public Coverage $coverage, array $alternateSets)
    {
        foreach ($alternateSets as $set) {
            if (!$set instanceof AlternateSet) {
                throw new \InvalidArgumentException('Every collection element must be a AlternateSet object.');
            }
        }

        if (count($alternateSets) !== $coverage->count()) {
            throw new MalformedFontException('Substitution set and coverage counts differ.');
        }
        $this->alternateSets = array_values($alternateSets);
    }

    public function lookupType(): int
    {
        return 3;
    }

    public function isSupported(): bool
    {
        return true;
    }
}
