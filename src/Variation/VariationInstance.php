<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Positioning\VariationDeltaResolver;
use PDFToolkit\OpenType\Positioning\VariationIndex;

/** Native ItemVariationStore evaluation for an explicitly selected normalized instance. */
final readonly class VariationInstance implements VariationDeltaResolver
{
    /** @var list<list<float>|null> */
    private array $deltas;

    public function __construct(public ItemVariationStore $store, public NormalizedCoordinates $coordinates)
    {
        if (count($coordinates->values) !== $store->axisCount) {
            throw new \InvalidArgumentException('The variation instance requires every store axis.');
        }
        $scalars = array_map(fn (VariationRegion $region): float => $region->scalar($coordinates), $store->regions);
        $values = [];
        foreach ($store->data as $data) {
            if ($data === null) {
                $values[] = null;
                continue;
            }
            $rows = [];
            foreach ($data->deltaSets as $deltas) {
                $value = 0.0;
                foreach ($data->regionIndices as $at => $region) {
                    $value += $deltas[$at] * $scalars[$region];
                }
                $rows[] = $value;
            }
            $values[] = $rows;
        }
        $this->deltas = $values;
    }

    public function delta(VariationIndex $index): float
    {
        if ($index->outerIndex === 65535 && $index->innerIndex === 65535) {
            return 0.0;
        }
        if (!array_key_exists($index->outerIndex, $this->deltas)) {
            throw new \PDFToolkit\OpenType\Exception\MalformedFontException('A variation outer index exceeds its store.');
        }
        $rows = $this->deltas[$index->outerIndex];
        return $rows === null ? 0.0 : ($rows[$index->innerIndex] ?? throw new \PDFToolkit\OpenType\Exception\MalformedFontException('A variation inner index exceeds its data.'));
    }
}
