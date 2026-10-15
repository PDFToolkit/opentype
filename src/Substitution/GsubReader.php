<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\Coverage;
use PDFToolkit\OpenType\Layout\CoverageFormat1;
use PDFToolkit\OpenType\Layout\CoverageFormat2;
use PDFToolkit\OpenType\Layout\Feature;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\FeatureRecord;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\LangSysRecord;
use PDFToolkit\OpenType\Layout\Lookup;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Layout\LookupList;
use PDFToolkit\OpenType\Layout\RangeRecord;
use PDFToolkit\OpenType\Layout\Script;
use PDFToolkit\OpenType\Layout\ScriptList;
use PDFToolkit\OpenType\Layout\ScriptRecord;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

/** Bounded GSUB 1.0/1.1 reader with executable substitution types 1 through 8. */
final readonly class GsubReader
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function read(BinaryView $table, int $glyphCount): GsubTable
    {
        if ($table->size() > $this->limits->maxBytes || $glyphCount < 1 || $glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('GSUB bytes or glyph count exceed the configured limits.');
        }
        $major = $table->uint16(0);
        $minor = $table->uint16(2);
        if ($major !== 1 || !in_array($minor, [0, 1], true)) {
            throw new UnsupportedFontException('Only GSUB versions 1.0 and 1.1 are supported.');
        }
        $headerSize = $minor === 1 ? 14 : 10;
        $table->bytes(0, $headerSize);
        $budget = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $lookupList = $this->lookups($this->child($table, $table->uint16(8), $headerSize), $glyphCount, $budget);
        $features = (new \PDFToolkit\OpenType\Layout\LayoutListsReader())->features($this->child($table, $table->uint16(6), $headerSize), $budget);
        $scripts = (new \PDFToolkit\OpenType\Layout\LayoutListsReader())->scripts($this->child($table, $table->uint16(4), $headerSize), $budget);
        $variations = null;
        if ($minor === 1 && $table->uint32(10) !== 0) {
            $variations = $this->child($table, $table->uint32(10), $headerSize);
            $this->featureVariations($variations, $features, count($lookupList->lookups), $budget);
        }

        return new GsubTable($scripts, $features, $lookupList, $glyphCount, $minor, $variations);
    }

    private function child(BinaryView $parent, int $offset, int $minimum): BinaryView
    {
        if ($offset < $minimum || $offset >= $parent->size()) {
            throw new MalformedFontException('A required GSUB offset is null, inside its header or beyond its table.');
        }

        return $parent->view($offset);
    }

    private function records(BinaryView $table, int $offset, int $count, int $width, LayoutReadBudget $budget): void
    {
        $budget->consume($count);
        $table->bytes($offset, $count * $width);
    }

    private function glyph(int $id, int $glyphCount): GlyphId
    {
        if ($id >= $glyphCount) {
            throw new MalformedFontException('GSUB refers to a glyph outside maxp.numGlyphs.');
        }

        return new GlyphId($id);
    }

    /** @return list<GlyphId> */
    private function glyphArray(BinaryView $table, int $offset, int $count, int $glyphCount, LayoutReadBudget $budget): array
    {
        $this->records($table, $offset, $count, 2, $budget);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $result[] = $this->glyph($table->uint16($offset + 2 * $i), $glyphCount);
        }

        return $result;
    }

    private function coverage(BinaryView $table, int $glyphCount, LayoutReadBudget $budget): Coverage
    {
        $format = $table->uint16(0);
        $count = $table->uint16(2);
        if ($format === 1) {
            return new CoverageFormat1($this->glyphArray($table, 4, $count, $glyphCount, $budget));
        }
        if ($format !== 2) {
            throw new MalformedFontException('Unknown coverage format.');
        }
        $this->records($table, 4, $count, 6, $budget);
        $ranges = [];
        $covered = 0;
        for ($i = 0; $i < $count; ++$i) {
            $offset = 4 + $i * 6;
            $range = new RangeRecord($this->glyph($table->uint16($offset), $glyphCount), $this->glyph($table->uint16($offset + 2), $glyphCount), $table->uint16($offset + 4));
            $covered += $range->count();
            $ranges[] = $range;
        }
        $budget->consume($covered);

        return new CoverageFormat2($ranges);
    }

    private function lookups(BinaryView $table, int $glyphCount, LayoutReadBudget $budget): LookupList
    {
        $count = $table->uint16(0);
        $this->records($table, 2, $count, 2, $budget);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $lookup = $this->child($table, $table->uint16(2 + 2 * $i), 2 + 2 * $count);
            $type = $lookup->uint16(0);
            if ($type < 1 || $type > 8) {
                throw new MalformedFontException('Unknown GSUB lookup type.');
            }
            $flags = $lookup->uint16(2);
            $subCount = $lookup->uint16(4);
            $this->records($lookup, 6, $subCount, 2, $budget);
            $headerSize = 6 + 2 * $subCount + (($flags & 0x0010) !== 0 ? 2 : 0);
            $lookup->bytes(0, $headerSize);
            $lookupFlags = new LookupFlags($flags, ($flags & 0x0010) !== 0 ? $lookup->uint16(6 + 2 * $subCount) : null);
            $subtables = [];
            for ($j = 0; $j < $subCount; ++$j) {
                $subtables[] = $this->subtable($this->child($lookup, $lookup->uint16(6 + 2 * $j), $headerSize), $type, $glyphCount, $count, $budget);
            }
            if ($subtables === []) {
                throw new MalformedFontException('A GSUB lookup must contain a subtable.');
            }
            $result[] = new Lookup($type, $lookupFlags, $subtables);
        }

        return new LookupList($result);
    }

    private function subtable(BinaryView $table, int $type, int $glyphCount, int $lookupCount, LayoutReadBudget $budget): SubstitutionSubtable
    {
        $format = $table->uint16(0);
        if ($type === 7) {
            if ($format !== 1) {
                throw new MalformedFontException('Unknown extension substitution format.');
            }
            $extensionType = $table->uint16(2);
            if ($extensionType < 1 || $extensionType > 8 || $extensionType === 7) {
                throw new MalformedFontException('An extension lookup cannot extend itself or an unknown type.');
            }

            return new ExtensionSubstFormat1($extensionType, $this->subtable($this->child($table, $table->uint32(4), 8), $extensionType, $glyphCount, $lookupCount, $budget));
        }
        if ($type === 5 || $type === 6) {
            [$coverage, $rules] = (new \PDFToolkit\OpenType\Layout\LayoutBinaryReader())->context($table, $type === 6, $glyphCount, $lookupCount, $budget);
            return new ContextSubstitution($type, $format, $coverage, $rules);
        }
        if ($type === 8) {
            if ($format !== 1) {
                throw new MalformedFontException('Unknown reverse chaining substitution format.');
            }
            $at = 4;
            $backtrack = $this->coverageOffsets($table, $at, $budget);
            $lookahead = $this->coverageOffsets($table, $at, $budget);
            $count = $table->uint16($at);
            $at += 2;
            $substitutes = $this->glyphArray($table, $at, $count, $glyphCount, $budget);
            $header = $at + 2 * $count;
            $coverage = $this->coverage($this->child($table, $table->uint16(2), $header), $glyphCount, $budget);
            $reader = new \PDFToolkit\OpenType\Layout\LayoutBinaryReader();
            if ($coverage->count() !== $count) {
                throw new MalformedFontException('Reverse substitution coverage and substitute counts differ.');
            }
            return new ReverseChainSingleSubstitution($coverage, new \PDFToolkit\OpenType\Layout\ContextRule([new \PDFToolkit\OpenType\Layout\CoveredGlyph($coverage)], [], $reader->coveragePredicates($table, $backtrack, $header, $glyphCount, $budget), $reader->coveragePredicates($table, $lookahead, $header, $glyphCount, $budget)), $substitutes);
        }
        if ($type === 1) {
            if ($format === 1) {
                $coverage = $this->coverage($this->child($table, $table->uint16(2), 6), $glyphCount, $budget);
                $single = new SingleSubstFormat1($coverage, $table->int16(4));
                foreach ($coverage->glyphs() as $input) {
                    $this->glyph(($input->value + $single->deltaGlyphId) & 0xFFFF, $glyphCount);
                }

                return $single;
            }
            if ($format !== 2) {
                throw new MalformedFontException('Unknown single substitution format.');
            }
            $count = $table->uint16(4);
            $glyphs = $this->glyphArray($table, 6, $count, $glyphCount, $budget);

            return new SingleSubstFormat2($this->coverage($this->child($table, $table->uint16(2), 6 + 2 * $count), $glyphCount, $budget), $glyphs);
        }
        if ($format !== 1) {
            throw new MalformedFontException('Unknown multiple, alternate or ligature substitution format.');
        }
        $count = $table->uint16(4);
        $this->records($table, 6, $count, 2, $budget);
        $coverage = $this->coverage($this->child($table, $table->uint16(2), 6 + 2 * $count), $glyphCount, $budget);
        $sequences = [];
        $alternateSets = [];
        $ligatureSets = [];
        for ($i = 0; $i < $count; ++$i) {
            $set = $this->child($table, $table->uint16(6 + 2 * $i), 6 + 2 * $count);
            $setCount = $set->uint16(0);
            if ($type === 2 || $type === 3) {
                $glyphs = $this->glyphArray($set, 2, $setCount, $glyphCount, $budget);
                if ($type === 2 && $glyphs === []) {
                    throw new MalformedFontException('Multiple substitution sequences must be non-empty.');
                }
                if ($type === 2) {
                    if ($glyphs === []) {
                        throw new MalformedFontException('Multiple substitution sequences must be non-empty.');
                    }
                    $sequences[] = new Sequence($glyphs);
                } else {
                    $alternateSets[] = new AlternateSet($glyphs);
                }
            } else {
                $this->records($set, 2, $setCount, 2, $budget);
                $ligatures = [];
                for ($j = 0; $j < $setCount; ++$j) {
                    $ligature = $this->child($set, $set->uint16(2 + 2 * $j), 2 + 2 * $setCount);
                    $componentCount = $ligature->uint16(2);
                    if ($componentCount < 1) {
                        throw new MalformedFontException('A ligature must have at least one component.');
                    }
                    $ligatures[] = new Ligature($this->glyph($ligature->uint16(0), $glyphCount), $this->glyphArray($ligature, 4, $componentCount - 1, $glyphCount, $budget));
                }
                $ligatureSets[] = new LigatureSet($ligatures);
            }
        }

        // Each branch has its own concrete immutable set type.
        if ($type === 2) {
            return new MultipleSubstFormat1($coverage, $sequences);
        }
        if ($type === 3) {
            return new AlternateSubstFormat1($coverage, $alternateSets);
        }
        return new LigatureSubstFormat1($coverage, $ligatureSets);
    }

    /** @return list<int> */
    private function coverageOffsets(BinaryView $table, int &$at, LayoutReadBudget $budget): array
    {
        $count = $table->uint16($at);
        $at += 2;
        $this->records($table, $at, $count, 2, $budget);
        $offsets = [];
        for ($i = 0; $i < $count; ++$i) {
            $offsets[] = $table->uint16($at + 2 * $i);
        }
        $at += 2 * $count;

        return $offsets;
    }

    /** Validate variation offsets and references; variation evaluation remains explicitly unsupported. */
    private function featureVariations(BinaryView $table, FeatureList $features, int $lookupCount, LayoutReadBudget $budget): void
    {
        if ($table->uint16(0) !== 1 || $table->uint16(2) !== 0) {
            throw new UnsupportedFontException('Unsupported FeatureVariations version.');
        }
        $count = $table->uint32(4);
        $this->records($table, 8, $count, 8, $budget);
        for ($i = 0; $i < $count; ++$i) {
            $at = 8 + 8 * $i;
            $conditionOffset = $table->uint32($at);
            if ($conditionOffset !== 0) {
                $conditions = $this->child($table, $conditionOffset, 8 + 8 * $count);
                $conditionCount = $conditions->uint16(0);
                $this->records($conditions, 2, $conditionCount, 4, $budget);
                for ($j = 0; $j < $conditionCount; ++$j) {
                    $condition = $this->child($conditions, $conditions->uint32(2 + 4 * $j), 2 + 4 * $conditionCount);
                    if ($condition->uint16(0) !== 1) {
                        throw new UnsupportedFontException('Unsupported feature-variation condition format.');
                    }
                    if ($condition->int16(4) > $condition->int16(6) || $condition->int16(4) < -16384 || $condition->int16(6) > 16384) {
                        throw new MalformedFontException('A variation condition has an invalid normalized range.');
                    }
                }
            }
            $substitutionOffset = $table->uint32($at + 4);
            if ($substitutionOffset === 0) {
                continue;
            }
            $substitutions = $this->child($table, $substitutionOffset, 8 + 8 * $count);
            if ($substitutions->uint16(0) !== 1 || $substitutions->uint16(2) !== 0) {
                throw new UnsupportedFontException('Unsupported FeatureTableSubstitution version.');
            }
            $subCount = $substitutions->uint16(4);
            $this->records($substitutions, 6, $subCount, 6, $budget);
            $previous = -1;
            for ($j = 0; $j < $subCount; ++$j) {
                $recordAt = 6 + 6 * $j;
                $featureIndex = $substitutions->uint16($recordAt);
                if ($featureIndex >= count($features->featureRecords) || $featureIndex <= $previous) {
                    throw new MalformedFontException('Feature substitutions refer to absent or unordered features.');
                }
                $previous = $featureIndex;
                $alternate = $this->child($substitutions, $substitutions->uint32($recordAt + 2), 6 + 6 * $subCount);
                $alternateFeature = (new \PDFToolkit\OpenType\Layout\LayoutListsReader())->feature($alternate, $features->featureRecords[$featureIndex]->featureTag, $budget);
                foreach ($alternateFeature->lookupListIndices as $lookupIndex) {
                    if ($lookupIndex >= $lookupCount) {
                        throw new MalformedFontException('A variation feature refers to an absent lookup.');
                    }
                }
            }
        }
    }
}
