<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class CvarTable
{
    /** @param list<TupleVariation> $variations */
    public function __construct(public int $axisCount, public int $controlValueCount, public array $variations)
    {
        if ($axisCount < 1 || $axisCount > 65535 || $controlValueCount < 0 || $controlValueCount > 65536 || !array_is_list($variations)) {
            throw new \InvalidArgumentException('Invalid cvar dimensions.');
        }
        foreach ($variations as $variation) {
            if (!$variation instanceof TupleVariation || count($variation->region->peak->values) !== $axisCount) {
                throw new \InvalidArgumentException('cvar region dimensions must match.');
            }
        }
    }

    public function instance(ControlValues $defaults, NormalizedCoordinates $coordinates, OutlineLimits $limits = new OutlineLimits()): ControlValues
    {
        if (count($defaults->values) !== $this->controlValueCount || count($coordinates->values) !== $this->axisCount) {
            throw new \InvalidArgumentException('cvar defaults and instance dimensions must match.');
        }
        $budget = new LayoutReadBudget($limits->maxOperations);
        $values = $defaults->values;
        foreach ($this->variations as $variation) {
            $budget->consume(1 + count($variation->deltas));
            $scalar = $variation->region->scalar($coordinates);
            if ($variation->points === null && count($variation->deltas) !== count($values)) {
                throw new MalformedFontException('An all-value cvar tuple must match the CVT.');
            }
            foreach ($variation->deltas as $at => $delta) {
                $point = $variation->points[$at] ?? $at;
                if (!isset($values[$point])) {
                    throw new MalformedFontException('A cvar delta exceeds its CVT.');
                }
                $values[$point] += $delta->x * $scalar;
            }
        }

        return new ControlValues($values);
    }
}
