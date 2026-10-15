<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Outline\OutlineLimits;
use PDFToolkit\OpenType\Sfnt\SfntFont;
use PDFToolkit\OpenType\Sfnt\SfntVersion;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\ItemVariationStoreReader;
use PDFToolkit\OpenType\Variation\FvarReader;

final readonly class CffReader
{
    public function __construct(private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function read(SfntFont $font): CffFont
    {
        if ($font->version !== SfntVersion::Cff) {
            throw new UnsupportedFontException('CFF requires an OTTO SFNT.');
        }
        $cff2 = $font->hasTable(new Tag('CFF2'));
        if ($cff2 && $font->hasTable(new Tag('CFF '))) {
            throw new MalformedFontException('A font must select exactly one CFF outline table.');
        }
        $table = $font->table(new Tag($cff2 ? 'CFF2' : 'CFF '));
        if ($table->size() > $this->limits->maxBytes || $table->uint8(0) !== ($cff2 ? 2 : 1) || $table->uint8(1) !== 0) {
            throw new UnsupportedFontException('Unsupported CFF version or exceeded byte budget.');
        }
        $header = $table->uint8(2);
        $budget = new LayoutReadBudget($this->limits->maxRecords);
        $bytesBudget = new LayoutReadBudget($this->limits->maxBytes);
        $indices = new CffIndexReader();
        $dicts = new CffDictReader();
        if ($cff2) {
            if ($header !== 5) {
                throw new MalformedFontException('CFF2 requires its five-byte header.');
            }
            $topSize = $table->uint16(3);
            $top = $dicts->read($table->view($header, $topSize), true, $budget);
            $global = $indices->read($table, $header + $topSize, true, $budget);
        } else {
            if ($header < 4 || $table->uint8(3) < 1 || $table->uint8(3) > 4) {
                throw new MalformedFontException('Invalid CFF header.');
            }
            $names = $indices->read($table, $header, false, $budget);
            $tops = $indices->read($table, $names->endOffset, false, $budget);
            if (count($names->objects) !== 1 || count($tops->objects) !== 1) {
                throw new MalformedFontException('An OpenType CFF table requires exactly one font.');
            }
            $top = $dicts->read($tops->objects[0], false, $budget);
            $strings = $indices->read($table, $tops->endOffset, false, $budget);
            $global = $indices->read($table, $strings->endOffset, false, $budget);
            if ($top->integer(1206, 2) !== 2) {
                throw new UnsupportedFontException('Only CFF Type 2 charstrings are supported.');
            }
        }
        if ($cff2) {
            $matrix = $top->operands(1207) ?? [0.001, 0.0, 0.0, 0.001, 0.0, 0.0];
            $scale = 1 / $font->table(new Tag('head'))->uint16(18);
            if (count($matrix) !== 6 || abs($matrix[0] - $scale) > 1e-12 || abs($matrix[3] - $scale) > 1e-12 || $matrix[1] != 0 || $matrix[2] != 0 || $matrix[4] != 0 || $matrix[5] != 0) {
                throw new MalformedFontException('CFF2 FontMatrix must match the reciprocal of unitsPerEm.');
            }
        }
        $minimum = $global->endOffset;
        $charOffset = $top->integer(17);
        $this->offset($table, $charOffset, $minimum);
        $charStrings = $indices->read($table, $charOffset, $cff2, $budget);
        if (count($charStrings->objects) !== $font->glyphCount()) {
            throw new MalformedFontException('CFF CharStrings and maxp glyph counts differ.');
        }
        $store = null;
        $storeOffset = $cff2 ? $top->integer(24, 0) : 0;
        if ($storeOffset !== 0) {
            $this->offset($table, $storeOffset, $minimum);
            $length = $table->uint16($storeOffset);
            $store = (new ItemVariationStoreReader())->read($table->view($storeOffset + 2, $length === 65535 ? null : $length), $budget);
            if (!$font->hasTable(new Tag('fvar')) || count((new FvarReader())->read($font->table(new Tag('fvar')))->axes) !== $store->axisCount) {
                throw new MalformedFontException('CFF2 variation axes must match fvar.');
            }
        }
        $fdOffset = $top->integer(1236, 0);
        if ($fdOffset === 0) {
            if ($cff2 || $top->operands(1230) !== null) {
                throw new MalformedFontException('CFF2 and CID-keyed CFF require FontDICTINDEX.');
            }
            $fontDictionaries = [$this->fontDictionary($top, $table, false, $minimum, $budget, $bytesBudget)];
            $selection = array_fill(0, $font->glyphCount(), 0);
        } else {
            $this->offset($table, $fdOffset, $minimum);
            $fontIndex = $indices->read($table, $fdOffset, $cff2, $budget);
            if ($fontIndex->objects === []) {
                throw new MalformedFontException('CFF FontDICTINDEX cannot be empty.');
            }
            $fontDictionaries = [];
            foreach ($fontIndex->objects as $dictionary) {
                $bytesBudget->consume($dictionary->size());
                $fontDictionaries[] = $this->fontDictionary($dicts->read($dictionary, $cff2, $budget), $table, $cff2, $minimum, $budget, $bytesBudget);
            }
            $selectOffset = $top->integer(1237, 0);
            if ($selectOffset === 0 && count($fontDictionaries) === 1 && $cff2) {
                $selection = array_fill(0, $font->glyphCount(), 0);
            } else {
                $this->offset($table, $selectOffset, $minimum);
                $selection = $this->selection($table->view($selectOffset), $cff2, $font->glyphCount(), count($fontDictionaries), $budget);
            }
        }

        $charset = $encoding = null;
        if (!$cff2) {
            $charsetOffset = $top->integer(15, 0);
            if ($charsetOffset > 2) {
                $this->offset($table, $charsetOffset, $minimum);
            }
            $charsetReader = new CffCharacterSetReader();
            $charset = $charsetReader->read($table, $charsetOffset, $top->operands(1230) !== null, $font->glyphCount(), $strings, $budget);
            if (!$charset->cidKeyed) {
                $encodingOffset = $top->integer(16, 0);
                if ($encodingOffset > 1) {
                    $this->offset($table, $encodingOffset, $minimum);
                }
                $encoding = $charsetReader->encoding($table, $encodingOffset, $charset, $budget);
            }
        }

        return new CffFont($cff2, $font->table(new Tag('head'))->uint16(18), $top, $charStrings, $global, $fontDictionaries, $selection, $store, $charset, $encoding);
    }

    private function fontDictionary(CffDictionary $dictionary, BinaryView $table, bool $cff2, int $minimum, LayoutReadBudget $budget, LayoutReadBudget $bytesBudget): CffFontDictionary
    {
        $private = $dictionary->operands(18);
        if ($private === null) {
            if ($cff2) {
                throw new MalformedFontException('A CFF2 FontDICT requires a PrivateDICT.');
            }

            return new CffFontDictionary($dictionary, new CffDictionary([]), new CffIndex([], 0));
        }
        if (count($private) !== 2 || $private[0] < 0 || $private[0] !== floor($private[0]) || $private[1] !== floor($private[1]) || $private[0] > $this->limits->maxBytes || $private[1] > $this->limits->maxBytes) {
            throw new MalformedFontException('A CFF PrivateDICT requires a bounded size and offset.');
        }
        $offset = (int) $private[1];
        $size = (int) $private[0];
        if ($cff2 && $size === 0 && $offset === 0) {
            return new CffFontDictionary($dictionary, new CffDictionary([]), new CffIndex([], 0));
        }
        $this->offset($table, $offset, $minimum, $size === 0);
        $bytesBudget->consume($size);
        $privateDict = (new CffDictReader())->read($table->view($offset, $size), $cff2, $budget);
        $subOffset = $privateDict->integer(19, 0);
        if ($subOffset !== 0 && ($subOffset < $size || $subOffset > $table->size() - $offset)) {
            throw new MalformedFontException('A local subroutine index overlaps or exceeds its private dictionary.');
        }
        $local = $subOffset === 0 ? new CffIndex([], 0) : (new CffIndexReader())->read($table, $offset + $subOffset, $cff2, $budget);

        return new CffFontDictionary($dictionary, $privateDict, $local, $privateDict->number(20), $privateDict->number(21), $cff2 ? $privateDict->integer(22, 0) : 0);
    }

    private function offset(BinaryView $table, int $offset, int $minimum, bool $allowEnd = false): void
    {
        if ($offset < $minimum || $offset > $table->size() || (!$allowEnd && $offset === $table->size())) {
            throw new MalformedFontException('A CFF structural offset overlaps its header or exceeds its table.');
        }
    }

    /** @return list<int> */
    private function selection(BinaryView $data, bool $cff2, int $glyphCount, int $fdCount, LayoutReadBudget $budget): array
    {
        $format = $data->uint8(0);
        $budget->consume($glyphCount);
        $selection = [];
        if ($format === 0) {
            $data->view(1, $glyphCount);
            for ($glyph = 0; $glyph < $glyphCount; ++$glyph) {
                $selection[] = $data->uint8(1 + $glyph);
            }
        } elseif ($format === 3 || ($format === 4 && $cff2)) {
            $wide = $format === 4;
            $count = $wide ? $data->uint32(1) : $data->uint16(1);
            $budget->consume($count);
            $at = $wide ? 5 : 3;
            $width = $wide ? 6 : 3;
            $data->view($at, $count * $width + ($wide ? 4 : 2));
            $ranges = [];
            for ($index = 0; $index < $count; ++$index) {
                $ranges[] = [$wide ? $data->uint32($at) : $data->uint16($at), $wide ? $data->uint16($at + 4) : $data->uint8($at + 2)];
                $at += $width;
            }
            $sentinel = $wide ? $data->uint32($at) : $data->uint16($at);
            if ($ranges === [] || $ranges[0][0] !== 0 || $sentinel !== $glyphCount) {
                throw new MalformedFontException('CFF FontDICTSelect must cover the complete glyph range.');
            }
            foreach ($ranges as $index => [$start, $fd]) {
                $end = $ranges[$index + 1][0] ?? $sentinel;
                if ($end <= $start || $end > $glyphCount) {
                    throw new MalformedFontException('CFF FontDICTSelect ranges must increase.');
                }
                for ($glyph = $start; $glyph < $end; ++$glyph) {
                    $selection[] = $fd;
                }
            }
        } else {
            throw new UnsupportedFontException('Unsupported FontDICTSelect format.');
        }
        foreach ($selection as $fd) {
            if ($fd >= $fdCount) {
                throw new MalformedFontException('A CFF glyph selects a missing FontDICT.');
            }
        }

        return $selection;
    }
}
