<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Layout\ClassDefinition;
use PDFToolkit\OpenType\Layout\Coverage;

final readonly class ClassPairAdjustment implements PositioningSubtable
{
    /** @param list<list<array{ValueRecord, ValueRecord}>> $classValues */
    public function __construct(public Coverage $coverage, public ClassDefinition $classDef1, public ClassDefinition $classDef2, public array $classValues, public bool $hasSecondValue = false)
    {
        if (!array_is_list($classValues) || $classValues === [] || $classValues[0] === []) {
            throw new \InvalidArgumentException('Class pair positioning requires a non-empty matrix.');
        }
        $width = count($classValues[0]);
        foreach ($classValues as $row) {
            if (!array_is_list($row) || count($row) !== $width) {
                throw new \InvalidArgumentException('Class pair matrices must be rectangular.');
            }
            foreach ($row as $pair) {
                $this->validatePair($pair);
            }
        }
        foreach ([[$classDef1, count($classValues)], [$classDef2, $width]] as [$definition, $count]) {
            foreach ($definition->ranges as $range) {
                if ($range->classValue >= $count) {
                    throw new \InvalidArgumentException('A pair class exceeds its matrix dimension.');
                }
            }
        }
    }

    private function validatePair(mixed $pair): void
    {
        if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2 || !$pair[0] instanceof ValueRecord || !$pair[1] instanceof ValueRecord) {
            throw new \InvalidArgumentException('Pair class cells contain two ValueRecord objects.');
        }
    }

    public function lookupType(): int
    {
        return 2;
    }
}
