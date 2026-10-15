<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\Coverage;

final readonly class SingleAdjustment implements PositioningSubtable
{
    /** @param list<ValueRecord> $values */
    public function __construct(public Coverage $coverage, public array $values, public int $format = 2)
    {
        if (!in_array($format, [1, 2], true) || count($values) !== ($format === 1 ? 1 : $coverage->count())) {
            throw new \InvalidArgumentException('Invalid single positioning value count.');
        }
        if (!array_is_list($values)) {
            throw new \InvalidArgumentException('Single positioning values must be ordered.');
        }
        foreach ($values as $value) {
            if (!$value instanceof ValueRecord) {
                throw new \InvalidArgumentException('Single adjustments require ValueRecord objects.');
            }
        }
    }

    public function lookupType(): int
    {
        return 1;
    }
}
