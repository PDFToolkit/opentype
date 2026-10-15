<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class ControlValueReader
{
    public function read(BinaryView $table): ControlValues
    {
        if (($table->size() & 1) !== 0 || $table->size() > 131072) {
            throw new MalformedFontException('The CVT must contain a bounded whole number of FWORD entries.');
        }
        $values = [];
        for ($at = 0; $at < $table->size(); $at += 2) {
            $values[] = (float) $table->int16($at);
        }

        return new ControlValues($values);
    }
}
