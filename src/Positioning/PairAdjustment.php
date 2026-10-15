<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\Coverage;

final readonly class PairAdjustment implements PositioningSubtable
{
    /** @param list<list<PairValueRecord>> $pairSets */
    public function __construct(public Coverage $coverage, public array $pairSets, public bool $hasSecondValue = false)
    {
        if (!array_is_list($pairSets) || count($pairSets) !== $coverage->count()) {
            throw new \InvalidArgumentException('Pair positioning sets must match coverage.');
        }
        foreach ($pairSets as $set) {
            if (!array_is_list($set)) {
                throw new \InvalidArgumentException('Pair sets must be ordered.');
            }
            $previous = -1;
            foreach ($set as $record) {
                if (!$record instanceof PairValueRecord || $record->secondGlyph->value <= $previous) {
                    throw new \InvalidArgumentException('Pair records must have sorted, unique second glyphs.');
                }
                $previous = $record->secondGlyph->value;
            }
        }
    }

    public function lookupType(): int
    {
        return 2;
    }
}
