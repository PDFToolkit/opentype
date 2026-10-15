<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\LayoutBinaryReader;
use PDFToolkit\OpenType\Layout\LayoutListsReader;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** Bounded native GPOS parsing; no external engine, file access or hidden feature selection. */
final readonly class GposReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits(), private LayoutBinaryReader $reader = new LayoutBinaryReader())
    {
    }

    public function read(BinaryView $table, int $glyphCount): GposTable
    {
        if ($table->size() > $this->limits->maxBytes || $glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('GPOS exceeds the configured byte or glyph budget.');
        }
        $minor = $table->uint16(2);
        if ($table->uint16(0) !== 1 || !in_array($minor, [0, 1], true)) {
            throw new UnsupportedFontException('Unsupported GPOS version.');
        }
        $header = $minor === 0 ? 10 : 14;
        $table->bytes(0, $header);
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $lists = new LayoutListsReader();
        $scripts = $lists->scripts($this->reader->child($table, $table->uint16(4), $header), $budget);
        $features = $lists->features($this->reader->child($table, $table->uint16(6), $header), $budget);
        $list = $this->reader->child($table, $table->uint16(8), $header);
        $count = $list->uint16(0);
        $this->reader->records($list, 2, $count, 2, $budget);
        $lookups = [];
        try {
            for ($i = 0; $i < $count; ++$i) {
                $lookup = $this->reader->child($list, $list->uint16(2 + 2 * $i), 2 + 2 * $count);
                $type = $lookup->uint16(0);
                if ($type < 1 || $type > 9) {
                    throw new MalformedFontException('Unknown GPOS lookup type.');
                }
                $flags = $lookup->uint16(2);
                $subCount = $lookup->uint16(4);
                $this->reader->records($lookup, 6, $subCount, 2, $budget);
                $size = 6 + 2 * $subCount + (($flags & 16) !== 0 ? 2 : 0);
                $lookup->bytes(0, $size);
                $subtables = [];
                for ($j = 0; $j < $subCount; ++$j) {
                    $subtables[] = $this->subtable($this->reader->child($lookup, $lookup->uint16(6 + 2 * $j), $size), $type, $glyphCount, $count, $budget);
                }
                if ($subtables === []) {
                    throw new MalformedFontException('A GPOS lookup cannot have an empty subtable list.');
                }
                $lookups[] = new PositioningLookup($type, new LookupFlags($flags, ($flags & 16) !== 0 ? $lookup->uint16(6 + 2 * $subCount) : null), $subtables);
            }
            $variations = $minor === 1 && $table->uint32(10) !== 0 ? $this->reader->child($table, $table->uint32(10), $header) : null;
            $variations = $variations === null ? null : (new \PDFToolkit\OpenType\Variation\FeatureVariationsReader())->read($variations, $features, count($lookups), $budget);
            return new GposTable($scripts, $features, $lookups, $glyphCount, $variations);
        } catch (\InvalidArgumentException $exception) {
            throw new MalformedFontException('Invalid GPOS object dimensions.', 0, $exception);
        }
    }

    private function formatWidth(int $format): int
    {
        if (($format & ~255) !== 0) {
            throw new MalformedFontException('Reserved GPOS value-format bits are set.');
        }
        $width = 0;
        for ($bit = 1; $bit <= 128; $bit <<= 1) {
            $width += ($format & $bit) !== 0 ? 2 : 0;
        }
        return $width;
    }

    private function value(BinaryView $parent, int &$at, int $format, int $minimum, LayoutReadBudget $budget): ValueRecord
    {
        $this->formatWidth($format);
        $values = [0, 0, 0, 0];
        $adjustments = [null, null, null, null];
        for ($i = 0; $i < 8; ++$i) {
            if (($format & (1 << $i)) === 0) {
                continue;
            }
            if ($i < 4) {
                $values[$i] = $parent->int16($at);
            } else {
                $adjustments[$i - 4] = (new AdjustmentReader())->read($parent, $parent->uint16($at), $minimum, $budget);
            }
            $at += 2;
        }
        return new ValueRecord(...[...$values, ...$adjustments]);
    }

    private function anchor(BinaryView $parent, int $offset, int $minimum, LayoutReadBudget $budget): ?Anchor
    {
        if ($offset === 0) {
            return null;
        }
        $budget->consume(1);
        $table = $this->reader->child($parent, $offset, $minimum);
        $format = $table->uint16(0);
        $x = $table->int16(2);
        $y = $table->int16(4);
        return match ($format) {
            1 => new Anchor($x, $y),
            2 => new Anchor($x, $y, $table->uint16(6)),
            3 => new Anchor($x, $y, null, (new AdjustmentReader())->read($table, $table->uint16(6), 10, $budget), (new AdjustmentReader())->read($table, $table->uint16(8), 10, $budget)),
            default => throw new MalformedFontException('Unknown GPOS anchor format.'),
        };
    }

    private function subtable(BinaryView $table, int $type, int $glyphCount, int $lookupCount, LayoutReadBudget $budget): PositioningSubtable
    {
        $format = $table->uint16(0);
        if ($type === 9) {
            if ($format !== 1 || $table->uint16(2) < 1 || $table->uint16(2) > 8) {
                throw new MalformedFontException('Invalid GPOS extension type or format.');
            }
            return new ExtensionPositioning($this->subtable($this->reader->child($table, $table->uint32(4), 8), $table->uint16(2), $glyphCount, $lookupCount, $budget));
        }
        if ($type === 7 || $type === 8) {
            [$coverage, $rules] = $this->reader->context($table, $type === 8, $glyphCount, $lookupCount, $budget);
            return new ContextPositioning($type, $format, $coverage, $rules);
        }
        if ($type === 1) {
            if (!in_array($format, [1, 2], true)) {
                throw new MalformedFontException('Unknown single positioning format.');
            }
            $valueFormat = $table->uint16(4);
            $width = $this->formatWidth($valueFormat);
            $count = $format === 1 ? 1 : $table->uint16(6);
            $at = $format === 1 ? 6 : 8;
            $header = $at + $count * $width;
            $this->reader->records($table, $at, $count, $width, $budget);
            $coverage = $this->reader->coverage($this->reader->child($table, $table->uint16(2), $header), $glyphCount, $budget);
            $values = [];
            for ($i = 0; $i < $count; ++$i) {
                $values[] = $this->value($table, $at, $valueFormat, $header, $budget);
            }
            return new SingleAdjustment($coverage, $values, $format);
        }
        if ($type === 2) {
            return $this->pair($table, $format, $glyphCount, $budget);
        }
        if ($format !== 1) {
            throw new MalformedFontException('Unknown cursive or mark attachment format.');
        }
        if ($type === 3) {
            $count = $table->uint16(4);
            $this->reader->records($table, 6, $count, 4, $budget);
            $header = 6 + 4 * $count;
            $coverage = $this->reader->coverage($this->reader->child($table, $table->uint16(2), $header), $glyphCount, $budget);
            $records = [];
            for ($i = 0; $i < $count; ++$i) {
                $records[] = new EntryExitRecord($this->anchor($table, $table->uint16(6 + 4 * $i), $header, $budget), $this->anchor($table, $table->uint16(8 + 4 * $i), $header, $budget));
            }
            return new CursiveAttachment($coverage, $records);
        }
        return $this->marks($table, $type, $glyphCount, $budget);
    }

    private function pair(BinaryView $table, int $format, int $glyphCount, LayoutReadBudget $budget): PairAdjustment|ClassPairAdjustment
    {
        if (!in_array($format, [1, 2], true)) {
            throw new MalformedFontException('Unknown pair positioning format.');
        }
        $format1 = $table->uint16(4);
        $format2 = $table->uint16(6);
        $width = $this->formatWidth($format1) + $this->formatWidth($format2);
        if ($format === 1) {
            $count = $table->uint16(8);
            $this->reader->records($table, 10, $count, 2, $budget);
            $header = 10 + 2 * $count;
            $coverage = $this->reader->coverage($this->reader->child($table, $table->uint16(2), $header), $glyphCount, $budget);
            $sets = [];
            for ($i = 0; $i < $count; ++$i) {
                $set = $this->reader->child($table, $table->uint16(10 + 2 * $i), $header);
                $pairs = $set->uint16(0);
                $this->reader->records($set, 2, $pairs, 2 + $width, $budget);
                $at = 2;
                $records = [];
                $previous = -1;
                for ($j = 0; $j < $pairs; ++$j) {
                    $id = $set->uint16($at);
                    if ($id <= $previous) {
                        throw new MalformedFontException('Pair-set second glyphs must be ordered and unique.');
                    }
                    $previous = $id;
                    $at += 2;
                    $first = $this->value($set, $at, $format1, 2 + $pairs * (2 + $width), $budget);
                    $second = $this->value($set, $at, $format2, 2 + $pairs * (2 + $width), $budget);
                    $records[] = new PairValueRecord($this->reader->glyph($id, $glyphCount), $first, $second);
                }
                $sets[] = $records;
            }
            return new PairAdjustment($coverage, $sets, $format2 !== 0);
        }
        $rows = $table->uint16(12);
        $columns = $table->uint16(14);
        if ($rows === 0 || $columns === 0) {
            throw new MalformedFontException('Pair class matrices cannot be empty.');
        }
        $this->reader->records($table, 16, $rows * $columns, $width, $budget);
        $header = 16 + $rows * $columns * $width;
        $coverage = $this->reader->coverage($this->reader->child($table, $table->uint16(2), $header), $glyphCount, $budget);
        $class1 = $this->reader->classes($this->reader->child($table, $table->uint16(8), $header), $glyphCount, $budget);
        $class2 = $this->reader->classes($this->reader->child($table, $table->uint16(10), $header), $glyphCount, $budget);
        $values = [];
        $at = 16;
        for ($i = 0; $i < $rows; ++$i) {
            $row = [];
            for ($j = 0; $j < $columns; ++$j) {
                $row[] = [$this->value($table, $at, $format1, $header, $budget), $this->value($table, $at, $format2, $header, $budget)];
            }
            $values[] = $row;
        }
        return new ClassPairAdjustment($coverage, $class1, $class2, $values, $format2 !== 0);
    }

    private function marks(BinaryView $table, int $type, int $glyphCount, LayoutReadBudget $budget): MarkAttachment
    {
        $markCoverage = $this->reader->coverage($this->reader->child($table, $table->uint16(2), 12), $glyphCount, $budget);
        $targetCoverage = $this->reader->coverage($this->reader->child($table, $table->uint16(4), 12), $glyphCount, $budget);
        $classes = $table->uint16(6);
        if ($classes === 0) {
            throw new MalformedFontException('Mark attachment requires at least one class.');
        }
        $marks = $this->reader->child($table, $table->uint16(8), 12);
        $count = $marks->uint16(0);
        $this->reader->records($marks, 2, $count, 4, $budget);
        $records = [];
        for ($i = 0; $i < $count; ++$i) {
            $anchor = $this->anchor($marks, $marks->uint16(4 + 4 * $i), 2 + 4 * $count, $budget);
            if ($anchor === null) {
                throw new MalformedFontException('A MarkRecord requires a mark anchor.');
            }
            $records[] = new MarkRecord($marks->uint16(2 + 4 * $i), $anchor);
        }
        $targets = $this->reader->child($table, $table->uint16(10), 12);
        $targetCount = $targets->uint16(0);
        $this->reader->records($targets, 2, $targetCount, $type === 5 ? 2 : 2 * $classes, $budget);
        $anchors = [];
        for ($i = 0; $i < $targetCount; ++$i) {
            $owner = $targets;
            $components = 1;
            $at = 2 + $i * 2 * $classes;
            $minimum = 2 + $targetCount * 2 * $classes;
            if ($type === 5) {
                $owner = $this->reader->child($targets, $targets->uint16(2 + 2 * $i), 2 + 2 * $targetCount);
                $components = $owner->uint16(0);
                if ($components === 0) {
                    throw new MalformedFontException('A LigatureAttach requires at least one component.');
                }
                $at = 2;
                $minimum = 2 + $components * $classes * 2;
                $this->reader->records($owner, 2, $components * $classes, 2, $budget);
            }
            $componentAnchors = [];
            for ($component = 0; $component < $components; ++$component) {
                $row = [];
                for ($class = 0; $class < $classes; ++$class) {
                    $row[] = $this->anchor($owner, $owner->uint16($at), $minimum, $budget);
                    $at += 2;
                }
                $componentAnchors[] = $row;
            }
            $anchors[] = new AttachmentAnchors($componentAnchors);
        }
        return new MarkAttachment($type, $markCoverage, $targetCoverage, $classes, $records, $anchors);
    }
}
