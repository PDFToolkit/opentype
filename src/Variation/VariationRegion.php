<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class VariationRegion
{
    /** @param list<RegionAxis> $axes */
    public function __construct(public array $axes)
    {
        if (!array_is_list($axes)) {
            throw new \InvalidArgumentException('Variation region axes must be ordered.');
        }
        foreach ($axes as $axis) {
            if (!$axis instanceof RegionAxis) {
                throw new \InvalidArgumentException('Variation regions require RegionAxis objects.');
            }
        }
    }

    public function scalar(NormalizedCoordinates $coordinates): float
    {
        if (count($coordinates->values) !== count($this->axes)) {
            throw new \InvalidArgumentException('The variation coordinate count differs from the region axis count.');
        }
        $scalar = 1.0;
        foreach ($this->axes as $index => $axis) {
            $scalar *= $axis->scalar($coordinates->axis($index));
        }
        return $scalar;
    }
}
