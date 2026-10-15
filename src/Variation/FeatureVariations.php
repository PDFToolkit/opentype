<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class FeatureVariations
{
    /** @param list<FeatureVariationRecord> $records */
    public function __construct(public array $records)
    {
        if (!array_is_list($records)) {
            throw new \InvalidArgumentException('Feature variation records must be ordered.');
        }
        foreach ($records as $record) {
            if (!$record instanceof FeatureVariationRecord) {
                throw new \InvalidArgumentException('Feature variations contain FeatureVariationRecord objects.');
            }
        }
    }

    public function evaluate(FeatureList $features, NormalizedCoordinates $coordinates, LayoutReadBudget $budget): FeatureList
    {
        foreach ($this->records as $record) {
            $budget->consume(1 + count($record->conditions));
            if (!$record->matches($coordinates)) {
                continue;
            }
            $replacements = $features->featureRecords;
            foreach ($record->substitutions as $index => $alternate) {
                $budget->consume(1 + count($alternate->lookupListIndices));
                $original = $replacements[$index] ?? throw new MalformedFontException('Feature variations refer to an absent feature.');
                $replacements[$index] = new FeatureRecord($original->featureTag, $alternate);
            }
            return new FeatureList(array_values($replacements));
        }
        return $features;
    }
}
