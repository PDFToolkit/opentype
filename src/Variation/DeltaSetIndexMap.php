<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Positioning\VariationIndex;

final readonly class DeltaSetIndexMap
{
    /** @param non-empty-list<VariationIndex> $entries */
    public function __construct(public int $format, public array $entries)
    {
        if (!in_array($format, [0, 1], true) || $entries === [] || !array_is_list($entries)) {
            throw new \InvalidArgumentException('A delta-set index map requires a known format and entries.');
        }
        foreach ($entries as $entry) {
            if (!$entry instanceof VariationIndex) {
                throw new \InvalidArgumentException('A delta-set mapping contains immutable indices.');
            }
        }
    }

    public function index(int $index): VariationIndex
    {
        if ($index < 0) {
            throw new \OutOfBoundsException('A mapping index cannot be negative.');
        }

        return $this->entries[min($index, count($this->entries) - 1)];
    }
}
