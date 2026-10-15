<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class CffIndexReader
{
    public function read(BinaryView $table, int $offset, bool $cff2, LayoutReadBudget $budget): CffIndex
    {
        $count = $cff2 ? $table->uint32($offset) : $table->uint16($offset);
        $at = $offset + ($cff2 ? 4 : 2);
        $budget->consume($count);
        if ($count === 0) {
            return new CffIndex([], $at);
        }
        $width = $table->uint8($at++);
        if ($width < 1 || $width > 4) {
            throw new MalformedFontException('CFF INDEX offsetSize must be in 1–4.');
        }
        $table->view($at, ($count + 1) * $width);
        $dataAt = $at + ($count + 1) * $width;
        $offsets = [];
        $previous = 1;
        for ($index = 0; $index <= $count; ++$index) {
            $value = 0;
            for ($byte = 0; $byte < $width; ++$byte) {
                $value = ($value << 8) | $table->uint8($at++);
            }
            if (($index === 0 && $value !== 1) || $value < $previous || $value - 1 > $table->size() - $dataAt) {
                throw new MalformedFontException('CFF INDEX offsets must start at 1 and increase within the table.');
            }
            $offsets[] = $value;
            $previous = $value;
        }
        $objects = [];
        for ($index = 0; $index < $count; ++$index) {
            $objects[] = $table->view($dataAt + $offsets[$index] - 1, ($offsets[$index + 1] ?? throw new MalformedFontException('Missing CFF end offset.')) - $offsets[$index]);
        }

        return new CffIndex($objects, $dataAt + $previous - 1);
    }
}
