<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class TupleVariationReader
{
    /** @param list<NormalizedCoordinates> $sharedTuples
     * @return list<TupleVariation>
     */
    public function read(BinaryView $table, int $axisCount, int $pointCount, array $sharedTuples, bool $twoDimensions, LayoutReadBudget $budget): array
    {
        $base = $twoDimensions ? 0 : 4;
        $packedCount = $table->uint16($base);
        $count = $packedCount & 0x0FFF;
        $dataAt = $table->uint16($base + 2);
        if (($packedCount & 0x7000) !== 0 || $pointCount < 0 || $pointCount > 65539) {
            throw new MalformedFontException('Invalid tuple-store flags or point count.');
        }
        $budget->consume($count);
        $at = $base + 4;
        $headers = [];
        for ($index = 0; $index < $count; ++$index) {
            $size = $table->uint16($at);
            $flags = $table->uint16($at + 2);
            $at += 4;
            if (($flags & 0x1000) !== 0 || (!$twoDimensions && ($flags & 0x8000) === 0)) {
                throw new MalformedFontException('Invalid tuple header flags or missing cvar embedded peak.');
            }
            $peak = ($flags & 0x8000) !== 0 ? $this->tuple($table, $at, $axisCount, $budget) : ($sharedTuples[$flags & 0x0FFF] ?? throw new MalformedFontException('A tuple refers to an absent shared peak.'));
            $start = $end = null;
            if (($flags & 0x4000) !== 0) {
                $start = $this->tuple($table, $at, $axisCount, $budget);
                $end = $this->tuple($table, $at, $axisCount, $budget);
            }
            $headers[] = [$size, $flags, new TupleRegion($peak, $start, $end)];
        }
        if ($dataAt < $at || $dataAt > $table->size()) {
            throw new MalformedFontException('Tuple serialized data overlaps or exceeds its header.');
        }
        $shared = ($packedCount & 0x8000) !== 0 ? $this->points($table, $dataAt, $pointCount, $budget) : null;
        $records = [];
        foreach ($headers as [$size, $flags, $region]) {
            $data = $table->view($dataAt, $size);
            $dataAt += $size;
            $at = 0;
            $points = ($flags & 0x2000) !== 0 ? $this->points($data, $at, $pointCount, $budget) : $shared;
            $number = $points === null ? $pointCount : count($points);
            $x = $this->deltas($data, $at, $number, $budget);
            $y = $twoDimensions ? $this->deltas($data, $at, $number, $budget) : array_fill(0, $number, 0);
            if ($at !== $size) {
                throw new MalformedFontException('Tuple serialized data contains unused bytes.');
            }
            $deltas = [];
            foreach ($x as $index => $value) {
                $deltas[] = new TupleDelta($value, $y[$index]);
            }
            $records[] = new TupleVariation($region, $points, $deltas);
        }
        if ($table->size() - $dataAt > 1 || ($table->size() > $dataAt && $table->uint8($dataAt) !== 0)) {
            throw new MalformedFontException('Unexpected trailing tuple-store data.');
        }

        return $records;
    }

    public function tuple(BinaryView $table, int &$at, int $axes, LayoutReadBudget $budget): NormalizedCoordinates
    {
        $budget->consume($axes);
        $table->view($at, $axes * 2);
        $values = [];
        for ($axis = 0; $axis < $axes; ++$axis) {
            $value = $table->int16($at) / 16384;
            $at += 2;
            if ($value < -1 || $value > 1) {
                throw new MalformedFontException('A tuple coordinate exceeds normalized bounds.');
            }
            $values[] = $value;
        }

        return new NormalizedCoordinates($values);
    }

    /** @return list<int>|null */
    private function points(BinaryView $data, int &$at, int $maximum, LayoutReadBudget $budget): ?array
    {
        $count = $data->uint8($at++);
        if ($count === 0) {
            return null;
        }
        if (($count & 128) !== 0) {
            $count = (($count & 127) << 8) | $data->uint8($at++);
        }
        if ($count === 0 || $count > $maximum) {
            throw new MalformedFontException('Packed point count exceeds its glyph or CVT.');
        }
        $budget->consume($count);
        $points = [];
        $previous = 0;
        while (count($points) < $count) {
            $control = $data->uint8($at++);
            $run = ($control & 127) + 1;
            if ($run > $count - count($points)) {
                throw new MalformedFontException('A packed point run exceeds its declared count.');
            }
            for ($index = 0; $index < $run; ++$index) {
                $delta = ($control & 128) !== 0 ? $data->uint16($at) : $data->uint8($at);
                $at += ($control & 128) !== 0 ? 2 : 1;
                $value = $previous + $delta;
                if ($value >= $maximum || ($points !== [] && $value <= $previous)) {
                    throw new MalformedFontException('Packed point indices must increase within their target.');
                }
                $points[] = $value;
                $previous = $value;
            }
        }

        return $points;
    }

    /** @return list<int> */
    private function deltas(BinaryView $data, int &$at, int $count, LayoutReadBudget $budget): array
    {
        $budget->consume($count);
        $values = [];
        while (count($values) < $count) {
            $control = $data->uint8($at++);
            $run = ($control & 63) + 1;
            if ($run > $count - count($values)) {
                throw new MalformedFontException('A packed delta run exceeds its declared count.');
            }
            for ($index = 0; $index < $run; ++$index) {
                if (($control & 128) !== 0) {
                    $value = 0;
                } elseif (($control & 64) !== 0) {
                    $value = $data->int16($at);
                    $at += 2;
                } else {
                    $value = $data->uint8($at++);
                    $value = $value >= 128 ? $value - 256 : $value;
                }
                $values[] = $value;
            }
        }

        return $values;
    }
}
