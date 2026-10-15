<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Positioning\VariationIndex;

final readonly class ItemVariationStore
{
    /** @param list<VariationRegion> $regions
     * @param list<ItemVariationData|null> $data
     */
    public function __construct(public int $axisCount, public array $regions, public array $data)
    {
        if ($axisCount < 0 || $axisCount > 65535 || !array_is_list($regions) || !array_is_list($data)) {
            throw new \InvalidArgumentException('Invalid variation store dimensions.');
        }
        foreach ($regions as $region) {
            if (!$region instanceof VariationRegion || count($region->axes) !== $axisCount) {
                throw new \InvalidArgumentException('Variation store regions must have matching axis counts.');
            }
        }
        foreach ($data as $record) {
            if ($record === null) {
                continue;
            }
            if (!$record instanceof ItemVariationData) {
                throw new \InvalidArgumentException('A variation store contains ItemVariationData objects.');
            }
            foreach ($record->regionIndices as $index) {
                if (!isset($regions[$index])) {
                    throw new MalformedFontException('Variation data refers to an absent region.');
                }
            }
        }
    }

    public function delta(VariationIndex $index, NormalizedCoordinates $coordinates): float
    {
        if ($index->outerIndex === 65535 && $index->innerIndex === 65535) {
            return 0.0;
        }
        if (count($coordinates->values) !== $this->axisCount) {
            throw new \InvalidArgumentException('The variation coordinate count differs from the store axis count.');
        }
        if (!array_key_exists($index->outerIndex, $this->data)) {
            throw new MalformedFontException('A variation outer index exceeds its store.');
        }
        $data = $this->data[$index->outerIndex];
        if ($data === null) {
            return 0.0;
        }
        $deltas = $data->deltaSets[$index->innerIndex] ?? throw new MalformedFontException('A variation inner index exceeds its data.');
        $value = 0.0;
        foreach ($data->regionIndices as $at => $region) {
            $value += $deltas[$at] * $this->regions[$region]->scalar($coordinates);
        }
        return $value;
    }
}
