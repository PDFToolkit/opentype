<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class CffCharacterSetReader
{
    public function read(BinaryView $table, int $offset, bool $cidKeyed, int $glyphs, CffIndex $strings, LayoutReadBudget $budget): CffCharacterSet
    {
        $budget->consume($glyphs);
        if ($offset <= 2) {
            if ($cidKeyed || $offset < 0) {
                throw new MalformedFontException('CID-keyed CFF requires an explicit charset.');
            }
            $predefined = match ($offset) {
                0 => CffStandardData::ISO_ADOBE, 1 => CffStandardData::EXPERT_CHARSET, default => CffStandardData::EXPERT_SUBSET
            };
            if ($glyphs > count($predefined)) {
                throw new MalformedFontException('The predefined CFF charset is too short.');
            }
            $identifiers = array_slice($predefined, 0, $glyphs);
        } else {
            $format = $table->uint8($offset++);
            $identifiers = [0];
            if ($format > 2) {
                throw new MalformedFontException('Invalid CFF charset format.');
            }
            while (count($identifiers) < $glyphs) {
                $first = $table->uint16($offset);
                $offset += 2;
                $left = $format === 0 ? 0 : ($format === 1 ? $table->uint8($offset++) : $table->uint16($offset));
                $offset += $format === 2 ? 2 : 0;
                if ($left >= $glyphs - count($identifiers) || $first + $left > 65535) {
                    throw new MalformedFontException('A CFF charset range exceeds its glyph count.');
                }
                for ($item = 0; $item <= $left; ++$item) {
                    $identifiers[] = $first + $item;
                }
            }
        }
        try {
            return new CffCharacterSet($cidKeyed, $identifiers, $strings);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Invalid CFF charset identifiers.', 0, $error);
        }
    }

    public function encoding(BinaryView $table, int $offset, CffCharacterSet $charset, LayoutReadBudget $budget): CffEncoding
    {
        if ($charset->cidKeyed || $offset < 0) {
            throw new MalformedFontException('CID-keyed CFF cannot use a name encoding.');
        }
        $codes = [];
        if ($offset < 2) {
            foreach ($offset === 0 ? CffStandardData::STANDARD_ENCODING : CffStandardData::EXPERT_ENCODING as $code => $sid) {
                $glyph = $sid === 0 ? null : $charset->glyph($sid);
                if ($glyph !== null) {
                    $codes[$code] = $glyph;
                }
            }
        } else {
            $format = $table->uint8($offset++);
            $base = $format & 127;
            if ($base > 1) {
                throw new MalformedFontException('Invalid CFF encoding format.');
            }
            $count = $table->uint8($offset++);
            $budget->consume($count);
            $glyph = 1;
            for ($index = 0; $index < $count; ++$index) {
                $first = $table->uint8($offset++);
                $left = $base === 0 ? 0 : $table->uint8($offset++);
                if ($first + $left > 255 || $glyph + $left >= count($charset->identifiers)) {
                    throw new MalformedFontException('A CFF encoding range exceeds its character or glyph bounds.');
                }
                $budget->consume($left);
                for ($at = 0; $at <= $left; ++$at) {
                    if (isset($codes[$first + $at])) {
                        throw new MalformedFontException('Duplicate CFF encoded character.');
                    }
                    $codes[$first + $at] = new GlyphId($glyph++);
                }
            }
            if (($format & 128) !== 0) {
                $count = $table->uint8($offset++);
                $budget->consume($count);
                for ($index = 0; $index < $count; ++$index) {
                    $code = $table->uint8($offset++);
                    $sid = $table->uint16($offset);
                    $offset += 2;
                    if (isset($codes[$code])) {
                        throw new MalformedFontException('Duplicate supplementary CFF code.');
                    }
                    $codes[$code] = $charset->glyph($sid) ?? throw new MalformedFontException('A CFF encoding supplement refers to an absent glyph.');
                }
            }
        }

        return new CffEncoding($codes);
    }
}
