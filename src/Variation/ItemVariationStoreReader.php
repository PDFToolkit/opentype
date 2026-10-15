<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\LayoutBinaryReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class ItemVariationStoreReader
{
    public function read(BinaryView $table, LayoutReadBudget $budget): ItemVariationStore
    {
        if ($table->uint16(0) !== 1) {
            throw new MalformedFontException('Unknown ItemVariationStore format.');
        }
        $reader = new LayoutBinaryReader();
        $count = $table->uint16(6);
        $reader->records($table, 8, $count, 4, $budget);
        $regionsTable = $reader->child($table, $table->uint32(2), 8 + 4 * $count);
        $axes = $regionsTable->uint16(0);
        $regionCount = $regionsTable->uint16(2);
        if (($regionCount & 0x8000) !== 0) {
            throw new MalformedFontException('The variation region count has a reserved high bit.');
        }
        $reader->records($regionsTable, 4, $axes * $regionCount, 6, $budget);
        $budget->consume($regionCount);
        $regions = [];
        $at = 4;
        for ($i = 0; $i < $regionCount; ++$i) {
            $regionAxes = [];
            for ($j = 0; $j < $axes; ++$j) {
                $values = [$regionsTable->int16($at), $regionsTable->int16($at + 2), $regionsTable->int16($at + 4)];
                foreach ($values as $value) {
                    if ($value < -16384 || $value > 16384) {
                        throw new MalformedFontException('Variation region coordinates exceed normalized bounds.');
                    }
                }
                $regionAxes[] = new RegionAxis($values[0] / 16384, $values[1] / 16384, $values[2] / 16384);
                $at += 6;
            }
            $regions[] = new VariationRegion($regionAxes);
        }
        $data = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = $table->uint32(8 + 4 * $i);
            if ($offset === 0) {
                $data[] = null;
                continue;
            }
            $record = $reader->child($table, $offset, 8 + 4 * $count);
            $items = $record->uint16(0);
            $words = $record->uint16(2);
            $long = ($words & 0x8000) !== 0;
            $words &= 0x7FFF;
            $indicesCount = $record->uint16(4);
            if ($words > $indicesCount) {
                throw new MalformedFontException('Variation word delta count exceeds the region count.');
            }
            $reader->records($record, 6, $indicesCount, 2, $budget);
            $indices = [];
            for ($j = 0; $j < $indicesCount; ++$j) {
                $index = $record->uint16(6 + 2 * $j);
                if ($index >= $regionCount) {
                    throw new MalformedFontException('Variation data refers to an absent region.');
                }
                $indices[] = $index;
            }
            $at = 6 + 2 * $indicesCount;
            $rowWidth = ($long ? 4 : 2) * $words + ($long ? 2 : 1) * ($indicesCount - $words);
            $reader->records($record, $at, $items, $rowWidth, $budget);
            $budget->consume($items * $indicesCount);
            $rows = [];
            for ($j = 0; $j < $items; ++$j) {
                $row = [];
                for ($k = 0; $k < $indicesCount; ++$k) {
                    $width = $k < $words ? ($long ? 4 : 2) : ($long ? 2 : 1);
                    $value = match ($width) {
                        4 => $record->uint32($at),
                        2 => $record->int16($at),
                        default => $record->uint8($at),
                    };
                    $row[] = $width === 4 && $value >= 0x80000000 ? $value - 0x100000000 : ($width === 1 && $value >= 128 ? $value - 256 : $value);
                    $at += $width;
                }
                $rows[] = $row;
            }
            $data[] = new ItemVariationData($indices, $rows);
        }
        return new ItemVariationStore($axes, $regions, $data);
    }
}
