<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class MvarTable
{
    /** @param list<MetricValueRecord> $records */
    public function __construct(public array $records, public ?ItemVariationStore $store)
    {
        if (!array_is_list($records) || (($records === []) !== ($store === null))) {
            throw new \InvalidArgumentException('MVAR requires a store exactly when it has value records.');
        }
        $previous = null;
        foreach ($records as $record) {
            if (!$record instanceof MetricValueRecord || ($previous !== null && strcmp($previous, $record->tag->value) >= 0)) {
                throw new \InvalidArgumentException('MVAR value tags must be sorted and unique.');
            }
            $previous = $record->tag->value;
            $index = $record->index;
            if ($index->outerIndex === 65535 && $index->innerIndex === 65535) {
                continue;
            }
            if ($store === null || !array_key_exists($index->outerIndex, $store->data)
                || ($store->data[$index->outerIndex] !== null && !isset($store->data[$index->outerIndex]->deltaSets[$index->innerIndex]))
            ) {
                throw new MalformedFontException('An MVAR value refers to an absent delta set.');
            }
        }
    }
}
