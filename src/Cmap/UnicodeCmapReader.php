<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class UnicodeCmapReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $glyphCount): UnicodeCharacterMap
    {
        if ($glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs || $table->size() > $this->limits->maxBytes) {
            throw new MalformedFontException('The cmap glyph or byte budget is exceeded.');
        }
        if ($table->uint16(0) !== 0) {
            throw new UnsupportedFontException('Only cmap version zero is supported.');
        }
        $count = $table->uint16(2);
        $remaining = $this->limits->maxLayoutRecords;
        $this->consume($remaining, $count);
        $directoryEnd = 4 + 8 * $count;
        $table->bytes(0, $directoryEnd);
        $selected = null;
        $selectedMap = null;
        $bestScore = -1;
        $variationSelectors = [];
        $seenVariation = false;
        /** @var array<int, SegmentedCharacterMap|SequentialCharacterMap> $maps */
        $maps = [];
        $previousKey = -1;
        for ($index = 0; $index < $count; ++$index) {
            $position = 4 + 8 * $index;
            $record = new EncodingRecord($table->uint16($position), $table->uint16($position + 2), $table->uint32($position + 4));
            $key = $record->platformId * 65536 + $record->encodingId;
            if ($key <= $previousKey) {
                throw new MalformedFontException('Cmap encoding records must be sorted and unique.');
            }
            $previousKey = $key;
            if ($record->offset < $directoryEnd) {
                throw new MalformedFontException('A cmap subtable cannot overlap its encoding directory.');
            }
            $format = $table->uint16($record->offset);
            if ($format === 14) {
                if ($record->platformId !== 0 || $record->encodingId !== 5 || $seenVariation) {
                    throw new MalformedFontException('Format 14 must occur once under Unicode platform 0, encoding 5.');
                }
                $variationSelectors = $this->readVariations($table->view($record->offset), $glyphCount, $remaining);
                $seenVariation = true;
                continue;
            }
            if ($record->platformId === 0 && $record->encodingId === 5) {
                throw new MalformedFontException('Unicode encoding 5 requires a format 14 subtable.');
            }
            $unicode = ($record->platformId === 0 && $record->encodingId <= 4)
                || ($record->platformId === 3 && in_array($record->encodingId, [1, 10], true));
            if (!$unicode || !in_array($format, [4, 12], true)) {
                continue;
            }
            if ($record->platformId === 3
                && (($record->encodingId === 1 && $format !== 4) || ($record->encodingId === 10 && $format !== 12))
            ) {
                throw new MalformedFontException('Windows Unicode encoding and cmap format do not agree.');
            }
            if (!isset($maps[$record->offset])) {
                $subtable = $table->view($record->offset);
                $maps[$record->offset] = $format === 12
                    ? $this->readSequential($subtable, $glyphCount, $remaining)
                    : $this->readSegments($subtable, $glyphCount, $remaining);
            }
            // Prefer full repertoire. Ties consistently prefer Windows, as fontTools getBestCmap does.
            $score = ($format === 12 ? 100 : 0) + ($record->platformId === 3 ? 10 : $record->encodingId);
            if ($score > $bestScore) {
                $bestScore = $score;
                $selected = $record;
                $selectedMap = $maps[$record->offset];
            }
        }
        if ($selected === null || $selectedMap === null) {
            throw new UnsupportedFontException('No supported Unicode cmap subtable (format 4 or 12) is present.');
        }

        return new UnicodeCharacterMap($selected, $selectedMap, $variationSelectors);
    }

    private function readSegments(BinaryView $source, int $glyphCount, int &$remaining): SegmentedCharacterMap
    {
        $length = $source->uint16(2);
        $table = $source->view(0, $length);
        $countX2 = $table->uint16(6);
        if ($countX2 < 2 || ($countX2 & 1) !== 0 || ($length & 1) !== 0 || $table->uint16(4) !== 0) {
            throw new MalformedFontException('Invalid format 4 segment count, byte length, or Unicode language.');
        }
        $count = intdiv($countX2, 2);
        $this->consume($remaining, $count);
        $arrayEnd = 16 + 8 * $count;
        $table->bytes(0, $arrayEnd);
        if ($table->uint16(14 + 2 * $count) !== 0) {
            throw new MalformedFontException('The format 4 reserved pad must be zero.');
        }
        $segments = [];
        $previousEnd = -1;
        for ($index = 0; $index < $count; ++$index) {
            $end = $table->uint16(14 + 2 * $index);
            $start = $table->uint16(16 + 2 * $count + 2 * $index);
            $delta = $table->int16(16 + 4 * $count + 2 * $index);
            $rangePosition = 16 + 6 * $count + 2 * $index;
            $rangeOffset = $table->uint16($rangePosition);
            if ($start > $end || $start <= $previousEnd || ($rangeOffset & 1) !== 0) {
                throw new MalformedFontException('Format 4 segments must be sorted, disjoint, and word-aligned.');
            }
            $previousEnd = $end;
            $glyphArray = null;
            if ($rangeOffset !== 0) {
                $glyphOffset = $rangePosition + $rangeOffset;
                if ($glyphOffset < $arrayEnd) {
                    throw new MalformedFontException('A format 4 range offset must address the glyph ID array.');
                }
                $table->bytes($glyphOffset, 2 * ($end - $start + 1));
                $glyphArray = [];
                for ($code = $start; $code <= $end; ++$code) {
                    $glyphArray[] = $table->uint16($glyphOffset + 2 * ($code - $start));
                }
            }
            $segment = new Segment($start, $end, $delta, $glyphArray);
            for ($code = $start; $code <= $end; ++$code) {
                if ($segment->glyphIndexFor($code) >= $glyphCount) {
                    throw new MalformedFontException('A format 4 mapping references a glyph outside maxp.numGlyphs.');
                }
            }
            $segments[] = $segment;
        }
        $last = $segments[$count - 1];
        if ($last->startCode !== 0xFFFF || $last->endCode !== 0xFFFF) {
            throw new MalformedFontException('Format 4 requires a final 0xFFFF segment.');
        }

        return new SegmentedCharacterMap($segments);
    }

    private function readSequential(BinaryView $source, int $glyphCount, int &$remaining): SequentialCharacterMap
    {
        $table = $source->view(0, $source->uint32(4));
        $count = $table->uint32(12);
        $this->consume($remaining, $count);
        if ($table->uint16(2) !== 0 || $table->uint32(8) !== 0 || $table->size() !== 16 + 12 * $count) {
            throw new MalformedFontException('Invalid format 12 reserved field, Unicode language, or declared length.');
        }
        $groups = [];
        $previousEnd = -1;
        for ($index = 0; $index < $count; ++$index) {
            $position = 16 + 12 * $index;
            $start = $table->uint32($position);
            $end = $table->uint32($position + 4);
            $glyph = $table->uint32($position + 8);
            if ($start > $end || $start <= $previousEnd || $end > 0x10FFFF || $glyph >= $glyphCount
                || $end - $start >= $glyphCount - $glyph
                || ($start <= 0xDFFF && $end >= 0xD800)
            ) {
                throw new MalformedFontException('Format 12 groups must contain ordered scalar ranges and glyph IDs within maxp.numGlyphs.');
            }
            $groups[] = new SequentialMapGroup(new CodePoint($start), new CodePoint($end), new GlyphId($glyph));
            $previousEnd = $end;
        }

        return new SequentialCharacterMap($groups);
    }

    /** @return list<VariationSelectorRecord> */
    private function readVariations(BinaryView $source, int $glyphCount, int &$remaining): array
    {
        $table = $source->view(0, $source->uint32(2));
        $count = $table->uint32(6);
        $this->consume($remaining, $count);
        $directoryEnd = 10 + 11 * $count;
        $table->bytes(0, $directoryEnd);
        $records = [];
        $previousSelector = -1;
        for ($index = 0; $index < $count; ++$index) {
            $position = 10 + 11 * $index;
            $selector = $table->uint24($position);
            $defaultOffset = $table->uint32($position + 3);
            $nonDefaultOffset = $table->uint32($position + 7);
            if ($selector <= $previousSelector) {
                throw new MalformedFontException('Format 14 selectors must be sorted and unique.');
            }
            $ranges = [];
            if ($defaultOffset !== 0) {
                if ($defaultOffset < $directoryEnd) {
                    throw new MalformedFontException('Default UVS data cannot overlap its selector directory.');
                }
                $rangeCount = $table->uint32($defaultOffset);
                $this->consume($remaining, $rangeCount);
                $table->bytes($defaultOffset + 4, 4 * $rangeCount);
                for ($range = 0; $range < $rangeCount; ++$range) {
                    $rangePosition = $defaultOffset + 4 + 4 * $range;
                    try {
                        $ranges[] = new UnicodeRange(new CodePoint($table->uint24($rangePosition)), $table->uint8($rangePosition + 3));
                    } catch (\InvalidArgumentException $error) {
                        throw new MalformedFontException('Invalid default UVS Unicode range.', 0, $error);
                    }
                }
            }
            $mappings = [];
            if ($nonDefaultOffset !== 0) {
                if ($nonDefaultOffset < $directoryEnd) {
                    throw new MalformedFontException('Non-default UVS data cannot overlap its selector directory.');
                }
                $mappingCount = $table->uint32($nonDefaultOffset);
                $this->consume($remaining, $mappingCount);
                $table->bytes($nonDefaultOffset + 4, 5 * $mappingCount);
                for ($mapping = 0; $mapping < $mappingCount; ++$mapping) {
                    $mappingPosition = $nonDefaultOffset + 4 + 5 * $mapping;
                    $glyph = $table->uint16($mappingPosition + 3);
                    if ($glyph >= $glyphCount) {
                        throw new MalformedFontException('A non-default UVS references a glyph outside maxp.numGlyphs.');
                    }
                    try {
                        $mappings[] = new UvsMapping(new CodePoint($table->uint24($mappingPosition)), new GlyphId($glyph));
                    } catch (\InvalidArgumentException $error) {
                        throw new MalformedFontException('Invalid non-default UVS Unicode scalar.', 0, $error);
                    }
                }
            }
            try {
                $records[] = new VariationSelectorRecord(new CodePoint($selector), $ranges, $mappings);
            } catch (\InvalidArgumentException $error) {
                throw new MalformedFontException('Invalid variation selector or overlapping UVS mappings.', 0, $error);
            }
            $previousSelector = $selector;
        }

        return $records;
    }

    private function consume(int &$remaining, int $count): void
    {
        if ($count > $remaining) {
            throw new MalformedFontException('The cmap record budget is exceeded.');
        }
        $remaining -= $count;
    }
}
