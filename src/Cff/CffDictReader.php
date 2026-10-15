<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

final readonly class CffDictReader
{
    public function read(BinaryView $data, bool $cff2, LayoutReadBudget $budget): CffDictionary
    {
        $stack = [];
        $entries = [];
        $reader = new CffNumberReader();
        $at = 0;
        while ($at < $data->size()) {
            $budget->consume(1);
            $byte = $data->uint8($at);
            if ($byte >= 28) {
                $stack[] = $reader->read($data, $at, true);
                if (count($stack) > ($cff2 ? 513 : 48)) {
                    throw new MalformedFontException('The CFF DICT operand stack exceeds its format limit.');
                }
            } else {
                ++$at;
                $operator = $byte === 12 ? 1200 + $data->uint8($at++) : $byte;
                $entries[] = new CffDictEntry($operator, $stack);
                $stack = [];
            }
        }
        if ($stack !== []) {
            throw new MalformedFontException('CFF DICT operands have no operator.');
        }

        return new CffDictionary($entries);
    }
}
