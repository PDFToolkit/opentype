<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\FvarTable;

final readonly class StyleAttributesReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, ?FvarTable $fvar = null): StyleAttributes
    {
        $minor = $table->uint16(2);
        if ($table->uint16(0) !== 1 || $minor > 2) {
            throw new UnsupportedFontException('Only STAT versions 1.0/1.1/1.2 are supported.');
        }
        $header = $minor === 0 ? 18 : 20;
        $axisSize = $table->uint16(4);
        $count = $table->uint16(6);
        $axisOffset = $table->uint32(8);
        $valueCount = $table->uint16(12);
        $valuesOffset = $table->uint32(14);
        if ($table->size() > $this->limits->maxBytes || ($count === 0 ? $axisOffset !== 0 || $valueCount > 0 : $axisOffset < $header || $axisSize < 8) || ($valueCount === 0 ? $valuesOffset !== 0 : $valuesOffset < $header)) {
            throw new MalformedFontException('Invalid STAT header offsets or dimensions.');
        }
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $budget->consume($count + $valueCount);
        $table->view($axisOffset, $count * $axisSize);
        $table->view($valuesOffset, 2 * $valueCount);
        try {
            $axes = [];
            for ($index = 0; $index < $count; ++$index) {
                $at = $axisOffset + $index * $axisSize;
                $axes[] = new StyleAxis(new Tag($table->bytes($at, 4)), $table->uint16($at + 4), $table->uint16($at + 6));
            }
            $values = [];
            for ($index = 0; $index < $valueCount; ++$index) {
                $offset = $table->uint16($valuesOffset + 2 * $index);
                if ($offset < 2 * $valueCount) {
                    throw new MalformedFontException('A STAT axis-value record overlaps its offset array.');
                }
                $values[] = $this->value($table->view($valuesOffset + $offset), $minor, $budget);
            }
            $result = new StyleAttributes($minor, $axes, $values, $minor === 0 ? null : $table->uint16(18));
            foreach (($fvar === null ? [] : $fvar->axes) as $axis) {
                $matching = array_filter($axes, fn (StyleAxis $style) => $style->tag->value === $axis->tag->value && $style->nameId === $axis->nameId);
                if ($matching === []) {
                    throw new MalformedFontException('STAT must include every fvar axis with the same name ID.');
                }
            }

            return $result;
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Malformed STAT records.', 0, $error);
        }
    }

    private function value(BinaryView $data, int $minor, LayoutReadBudget $budget): StyleValue
    {
        $format = StyleValueFormat::tryFrom($data->uint16(0)) ?? throw new UnsupportedFontException('Unknown STAT axis-value format.');
        if ($format === StyleValueFormat::Combination) {
            if ($minor < 2) {
                throw new MalformedFontException('STAT format 4 requires table version 1.2.');
            }
            $count = $data->uint16(2);
            $budget->consume($count);
            $data->view(8, 6 * $count);
            $coordinates = [];
            for ($index = 0; $index < $count; ++$index) {
                $coordinates[] = new StyleCoordinate($data->uint16(8 + 6 * $index), $this->fixed($data, 10 + 6 * $index));
            }

            return new StyleValue($format, $data->uint16(6), $data->uint16(4), $coordinates);
        }
        $coordinate = new StyleCoordinate($data->uint16(2), $this->fixed($data, 8));

        return new StyleValue($format, $data->uint16(6), $data->uint16(4), [$coordinate], $format === StyleValueFormat::Range ? $this->fixed($data, 12) : null, $format === StyleValueFormat::Range ? $this->fixed($data, 16) : null, $format === StyleValueFormat::Linked ? $this->fixed($data, 12) : null);
    }

    private function fixed(BinaryView $data, int $at): float
    {
        $value = $data->uint32($at);

        return ($value >= 0x80000000 ? $value - 0x100000000 : $value) / 65536.0;
    }
}
