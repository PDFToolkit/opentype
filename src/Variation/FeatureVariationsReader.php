<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\LayoutBinaryReader;
use PDFToolkit\OpenType\Layout\LayoutListsReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class FeatureVariationsReader
{
    public function read(BinaryView $table, FeatureList $features, int $lookupCount, LayoutReadBudget $budget): FeatureVariations
    {
        if ($table->uint16(0) !== 1 || $table->uint16(2) !== 0) {
            throw new UnsupportedFontException('Unsupported FeatureVariations version.');
        }
        $reader = new LayoutBinaryReader();
        $lists = new LayoutListsReader();
        $count = $table->uint32(4);
        $reader->records($table, 8, $count, 8, $budget);
        $records = [];
        for ($i = 0; $i < $count; ++$i) {
            $conditionOffset = $table->uint32(8 + 8 * $i);
            $conditions = [];
            if ($conditionOffset !== 0) {
                $set = $reader->child($table, $conditionOffset, 8 + 8 * $count);
                $n = $set->uint16(0);
                $reader->records($set, 2, $n, 4, $budget);
                for ($j = 0; $j < $n; ++$j) {
                    $condition = $reader->child($set, $set->uint32(2 + 4 * $j), 2 + 4 * $n);
                    if ($condition->uint16(0) !== 1) {
                        $conditions[] = new UnknownFeatureCondition($condition->uint16(0));
                        continue;
                    }
                    $minimum = $condition->int16(4);
                    $maximum = $condition->int16(6);
                    if ($minimum < -16384 || $maximum > 16384 || $minimum > $maximum) {
                        throw new MalformedFontException('Invalid normalized feature variation range.');
                    }
                    $conditions[] = new VariationCondition($condition->uint16(2), $minimum / 16384, $maximum / 16384);
                }
            }
            $substitutions = [];
            $offset = $table->uint32(12 + 8 * $i);
            if ($offset !== 0) {
                $set = $reader->child($table, $offset, 8 + 8 * $count);
                if ($set->uint16(0) !== 1 || $set->uint16(2) !== 0) {
                    $records[] = new FeatureVariationRecord($conditions, [], false);
                    continue;
                }
                $n = $set->uint16(4);
                $reader->records($set, 6, $n, 6, $budget);
                $previous = -1;
                for ($j = 0; $j < $n; ++$j) {
                    $at = 6 + 6 * $j;
                    $index = $set->uint16($at);
                    if ($index <= $previous || !isset($features->featureRecords[$index])) {
                        throw new MalformedFontException('Feature substitutions refer to absent or unordered feature indices.');
                    }
                    $previous = $index;
                    $alternate = $lists->feature($reader->child($set, $set->uint32($at + 2), 6 + 6 * $n), $features->featureRecords[$index]->featureTag, $budget);
                    foreach ($alternate->lookupListIndices as $lookupIndex) {
                        if ($lookupIndex >= $lookupCount) {
                            throw new MalformedFontException('A variation alternate refers to an absent lookup.');
                        }
                    }
                    $substitutions[$index] = $alternate;
                }
            }
            $records[] = new FeatureVariationRecord($conditions, $substitutions);
        }
        return new FeatureVariations($records);
    }
}
