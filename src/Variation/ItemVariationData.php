<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class ItemVariationData
{
    /** @param list<int> $regionIndices
     * @param list<list<int>> $deltaSets
     */
    public function __construct(public array $regionIndices, public array $deltaSets)
    {
        if (!array_is_list($regionIndices) || !array_is_list($deltaSets)) {
            throw new \InvalidArgumentException('Variation records must be ordered.');
        }
        foreach ($regionIndices as $index) {
            if (!is_int($index) || $index < 0 || $index > 65535) {
                throw new \InvalidArgumentException('Variation region indices must be unsigned 16-bit values.');
            }
        }
        foreach ($deltaSets as $deltas) {
            if (!array_is_list($deltas) || count($deltas) !== count($regionIndices)) {
                throw new \InvalidArgumentException('One delta is required for each referenced region.');
            }
            foreach ($deltas as $delta) {
                if (!is_int($delta) || $delta < -2147483648 || $delta > 2147483647) {
                    throw new \InvalidArgumentException('Variation deltas must fit signed 32-bit values.');
                }
            }
        }
    }
}
