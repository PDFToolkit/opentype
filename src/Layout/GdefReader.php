<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** Bounded GDEF classifications, attachment points, ligature carets and item variation data. */
final readonly class GdefReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $glyphCount): GdefTable
    {
        if ($table->size() > $this->limits->maxBytes || $glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('GDEF exceeds the configured byte or glyph budget.');
        }
        $minor = $table->uint16(2);
        if ($table->uint16(0) !== 1 || !in_array($minor, [0, 2, 3], true)) {
            throw new UnsupportedFontException('Unsupported GDEF version.');
        }
        $header = $minor === 0 ? 12 : ($minor === 2 ? 14 : 18);
        $table->bytes(0, $header);
        $reader = new LayoutBinaryReader();
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $glyphClasses = $table->uint16(4) === 0 ? new ClassDefinition() : $reader->classes($reader->child($table, $table->uint16(4), $header), $glyphCount, $budget);
        $markClasses = $table->uint16(10) === 0 ? new ClassDefinition() : $reader->classes($reader->child($table, $table->uint16(10), $header), $glyphCount, $budget);
        $sets = [];
        if ($minor >= 2 && $table->uint16(12) !== 0) {
            $markSets = $reader->child($table, $table->uint16(12), $header);
            if ($markSets->uint16(0) !== 1) {
                throw new MalformedFontException('Unknown MarkGlyphSets format.');
            }
            $count = $markSets->uint16(2);
            $reader->records($markSets, 4, $count, 4, $budget);
            for ($i = 0; $i < $count; ++$i) {
                $sets[] = $reader->coverage($reader->child($markSets, $markSets->uint32(4 + 4 * $i), 4 + 4 * $count), $glyphCount, $budget);
            }
        }
        $points = $table->uint16(6) === 0 ? [] : $this->attachList($reader->child($table, $table->uint16(6), $header), $glyphCount, $budget, $reader);
        $carets = $table->uint16(8) === 0 ? [] : $this->caretList($reader->child($table, $table->uint16(8), $header), $glyphCount, $budget, $reader);
        $store = $minor === 3 && $table->uint32(14) !== 0 ? (new \PDFToolkit\OpenType\Variation\ItemVariationStoreReader())->read($reader->child($table, $table->uint32(14), $header), $budget) : null;
        return new GdefTable($glyphClasses, $markClasses, $sets, $points, $carets, $store);
    }

    /** @return array<int, list<int>> */
    private function attachList(BinaryView $table, int $glyphCount, LayoutReadBudget $budget, LayoutBinaryReader $reader): array
    {
        $count = $table->uint16(2);
        $reader->records($table, 4, $count, 2, $budget);
        $coverage = $reader->coverage($reader->child($table, $table->uint16(0), 4 + 2 * $count), $glyphCount, $budget);
        if ($coverage->count() !== $count) {
            throw new MalformedFontException('GDEF attachment coverage and point-list counts differ.');
        }
        $points = [];
        foreach ($coverage->glyphs() as $index => $glyph) {
            $record = $reader->child($table, $table->uint16(4 + 2 * $index), 4 + 2 * $count);
            $n = $record->uint16(0);
            $reader->records($record, 2, $n, 2, $budget);
            $values = [];
            $previous = -1;
            for ($i = 0; $i < $n; ++$i) {
                $value = $record->uint16(2 + 2 * $i);
                if ($value <= $previous) {
                    throw new MalformedFontException('GDEF attachment point indices must be ordered and unique.');
                }
                $previous = $value;
                $values[] = $value;
            }
            $points[$glyph->value] = $values;
        }
        return $points;
    }

    /** @return array<int, list<CaretValue>> */
    private function caretList(BinaryView $table, int $glyphCount, LayoutReadBudget $budget, LayoutBinaryReader $reader): array
    {
        $count = $table->uint16(2);
        $reader->records($table, 4, $count, 2, $budget);
        $coverage = $reader->coverage($reader->child($table, $table->uint16(0), 4 + 2 * $count), $glyphCount, $budget);
        if ($coverage->count() !== $count) {
            throw new MalformedFontException('GDEF caret coverage and ligature counts differ.');
        }
        $carets = [];
        foreach ($coverage->glyphs() as $index => $glyph) {
            $record = $reader->child($table, $table->uint16(4 + 2 * $index), 4 + 2 * $count);
            $n = $record->uint16(0);
            $reader->records($record, 2, $n, 2, $budget);
            $values = [];
            for ($i = 0; $i < $n; ++$i) {
                $caret = $reader->child($record, $record->uint16(2 + 2 * $i), 2 + 2 * $n);
                $values[] = match ($caret->uint16(0)) {
                    1 => new CaretValue($caret->int16(2)),
                    2 => new CaretValue(caretValuePointIndex: $caret->uint16(2)),
                    3 => new CaretValue($caret->int16(2), adjustment: (new \PDFToolkit\OpenType\Positioning\AdjustmentReader())->read($caret, $caret->uint16(4), 6, $budget)),
                    default => throw new MalformedFontException('Unknown GDEF caret format.'),
                };
            }
            $carets[$glyph->value] = $values;
        }
        return $carets;
    }
}
