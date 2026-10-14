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

/** GSUB 1.0/1.1 structure reader; contextual rules are validated and retained, not executed. */
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
        $features = $this->features($this->child($table, $table->uint16(6), $headerSize), $budget);
        $scripts = $this->scripts($this->child($table, $table->uint16(4), $headerSize), $budget);
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

    private function tag(BinaryView $table, int $offset): Tag
    {
        try {
            return new Tag($table->bytes($offset, 4));
        } catch (\InvalidArgumentException $exception) {
            throw new MalformedFontException('Invalid OpenType layout tag.', 0, $exception);
        }
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

    private function scripts(BinaryView $table, LayoutReadBudget $budget): ScriptList
    {
        $count = $table->uint16(0);
        $this->records($table, 2, $count, 6, $budget);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = 2 + 6 * $i;
            $script = $this->child($table, $table->uint16($offset + 4), 2 + 6 * $count);
            $langCount = $script->uint16(2);
            $this->records($script, 4, $langCount, 6, $budget);
            $default = $script->uint16(0) === 0 ? null : $this->language($this->child($script, $script->uint16(0), 4 + 6 * $langCount), $budget);
            $languages = [];
            for ($j = 0; $j < $langCount; ++$j) {
                $at = 4 + 6 * $j;
                $languages[] = new LangSysRecord($this->tag($script, $at), $this->language($this->child($script, $script->uint16($at + 4), 4 + 6 * $langCount), $budget));
            }
            $result[] = new ScriptRecord($this->tag($table, $offset), new Script($default, $languages));
        }

        return new ScriptList($result);
    }

    private function language(BinaryView $table, LayoutReadBudget $budget): LangSys
    {
        if ($table->uint16(0) !== 0) {
            throw new MalformedFontException('LangSys.lookupOrderOffset is reserved and must be null.');
        }
        $required = $table->uint16(2);
        $count = $table->uint16(4);
        $this->records($table, 6, $count, 2, $budget);
        $indices = [];
        for ($i = 0; $i < $count; ++$i) {
            $indices[] = $table->uint16(6 + 2 * $i);
        }

        return new LangSys($required === 0xFFFF ? null : $required, $indices);
    }

    private function features(BinaryView $table, LayoutReadBudget $budget): FeatureList
    {
        $count = $table->uint16(0);
        $this->records($table, 2, $count, 6, $budget);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $offset = 2 + 6 * $i;
            $tag = $this->tag($table, $offset);
            $feature = $this->child($table, $table->uint16($offset + 4), 2 + 6 * $count);
            $result[] = new FeatureRecord($tag, $this->feature($feature, $tag, $budget));
        }

        return new FeatureList($result);
    }

    private function feature(BinaryView $table, Tag $tag, LayoutReadBudget $budget): Feature
    {
        $count = $table->uint16(2);
        $this->records($table, 4, $count, 2, $budget);
        $indices = [];
        for ($i = 0; $i < $count; ++$i) {
            $indices[] = $table->uint16(4 + 2 * $i);
        }
        $params = $table->uint16(0) === 0 ? null : $this->child($table, $table->uint16(0), 4 + 2 * $count);
        if ($params !== null) {
            $this->featureParams($params, $tag, $budget);
        }

        return new Feature($indices, $params);
    }

    private function featureParams(BinaryView $table, Tag $tag, LayoutReadBudget $budget): void
    {
        if ($tag->value === 'size') {
            $table->bytes(0, 10);

            return;
        }
        if (preg_match('/^ss(?:0[1-9]|1[0-9]|20)$/D', $tag->value) === 1) {
            $table->bytes(0, 4);
            if ($table->uint16(0) !== 0) {
                throw new UnsupportedFontException('Unsupported stylistic-set feature parameters version.');
            }

            return;
        }
        if (preg_match('/^cv(?:0[1-9]|[1-9][0-9])$/D', $tag->value) !== 1) {
            throw new MalformedFontException('This feature tag does not define feature parameters.');
        }
        if ($table->uint16(0) !== 0) {
            throw new UnsupportedFontException('Unsupported character-variant feature parameters version.');
        }
        $count = $table->uint16(12);
        $this->records($table, 14, $count, 3, $budget);
        for ($i = 0; $i < $count; ++$i) {
            $code = $table->uint24(14 + 3 * $i);
            if ($code > 0x10FFFF || ($code >= 0xD800 && $code <= 0xDFFF)) {
                throw new MalformedFontException('Feature parameters contain an invalid Unicode scalar.');
            }
        }
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
        if (in_array($type, [5, 6, 8], true)) {
            $this->validateContext($table, $type, $format, $glyphCount, $lookupCount, $budget);

            return new UnsupportedSubstitution($type, $format, $table);
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

    /** Validate unsupported contextual structures without invoking nested lookups. */
    private function validateContext(BinaryView $table, int $type, int $format, int $glyphCount, int $lookupCount, LayoutReadBudget $budget): void
    {
        if ($format < 1 || $format > ($type === 8 ? 1 : 3)) {
            throw new MalformedFontException('Unknown contextual substitution format.');
        }
        if ($type === 8) {
            $at = 4;
            $backtrack = $this->coverageOffsets($table, $at, $budget);
            $lookahead = $this->coverageOffsets($table, $at, $budget);
            $count = $table->uint16($at);
            $at += 2;
            $this->glyphArray($table, $at, $count, $glyphCount, $budget);
            $header = $at + 2 * $count;
            $this->validateCoverageOffsets($table, $backtrack, $header, $glyphCount, $budget);
            $this->validateCoverageOffsets($table, $lookahead, $header, $glyphCount, $budget);
            $coverage = $this->coverage($this->child($table, $table->uint16(2), $header), $glyphCount, $budget);
            if ($coverage->count() !== $count) {
                throw new MalformedFontException('Reverse substitution and coverage counts differ.');
            }

            return;
        }
        if ($format === 3) {
            $at = 2;
            if ($type === 6) {
                $backtrack = $this->coverageOffsets($table, $at, $budget);
                $input = $this->coverageOffsets($table, $at, $budget);
                $lookahead = $this->coverageOffsets($table, $at, $budget);
                $inputCount = count($input);
                $recordCount = $table->uint16($at);
                $at += 2;
                $header = $at + 4 * $recordCount;
                $this->validateCoverageOffsets($table, $backtrack, $header, $glyphCount, $budget);
                $this->validateCoverageOffsets($table, $input, $header, $glyphCount, $budget);
                $this->validateCoverageOffsets($table, $lookahead, $header, $glyphCount, $budget);
            } else {
                $inputCount = $table->uint16(2);
                $recordCount = $table->uint16(4);
                $at = 6;
                $this->records($table, $at, $inputCount, 2, $budget);
                $header = $at + 2 * $inputCount + 4 * $recordCount;
                for ($i = 0; $i < $inputCount; ++$i) {
                    $this->coverage($this->child($table, $table->uint16($at + 2 * $i), $header), $glyphCount, $budget);
                }
                $at += 2 * $inputCount;
            }
            if ($inputCount < 1) {
                throw new MalformedFontException('A contextual input sequence cannot be empty.');
            }
            $this->lookupRecords($table, $at, $recordCount, $inputCount, $lookupCount, $budget);

            return;
        }
        $countAt = $format === 1 ? 4 : ($type === 5 ? 6 : 10);
        $setCount = $table->uint16($countAt);
        $arrayAt = $countAt + 2;
        $this->records($table, $arrayAt, $setCount, 2, $budget);
        $header = $arrayAt + 2 * $setCount;
        $coverage = $this->coverage($this->child($table, $table->uint16(2), $header), $glyphCount, $budget);
        if ($format === 1 && $setCount !== $coverage->count()) {
            throw new MalformedFontException('Context rule-set and coverage counts differ.');
        }
        if ($format === 2) {
            for ($at = 4; $at < $countAt; $at += 2) {
                $this->classDefinition($this->child($table, $table->uint16($at), $header), $glyphCount, $budget);
            }
        }
        for ($i = 0; $i < $setCount; ++$i) {
            $offset = $table->uint16($arrayAt + 2 * $i);
            if ($offset === 0) {
                continue;
            }
            $set = $this->child($table, $offset, $header);
            $rules = $set->uint16(0);
            $this->records($set, 2, $rules, 2, $budget);
            for ($j = 0; $j < $rules; ++$j) {
                $rule = $this->child($set, $set->uint16(2 + 2 * $j), 2 + 2 * $rules);
                $at = 0;
                if ($type === 6) {
                    $this->contextValues($rule, $at, 0, $format === 1 ? $glyphCount : null, $budget);
                    $inputCount = $this->contextValues($rule, $at, 1, $format === 1 ? $glyphCount : null, $budget);
                    $this->contextValues($rule, $at, 0, $format === 1 ? $glyphCount : null, $budget);
                    $recordCount = $rule->uint16($at);
                    $at += 2;
                } else {
                    $inputCount = $rule->uint16(0);
                    $recordCount = $rule->uint16(2);
                    $at = 4;
                    if ($inputCount < 1) {
                        throw new MalformedFontException('A contextual input sequence cannot be empty.');
                    }
                    $this->contextArray($rule, $at, $inputCount - 1, $format === 1 ? $glyphCount : null, $budget);
                    $at += 2 * ($inputCount - 1);
                }
                $this->lookupRecords($rule, $at, $recordCount, $inputCount, $lookupCount, $budget);
            }
        }
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

    /** @param list<int> $offsets */
    private function validateCoverageOffsets(BinaryView $table, array $offsets, int $minimum, int $glyphCount, LayoutReadBudget $budget): void
    {
        foreach ($offsets as $offset) {
            $this->coverage($this->child($table, $offset, $minimum), $glyphCount, $budget);
        }
    }

    private function contextValues(BinaryView $table, int &$at, int $implicit, ?int $glyphCount, LayoutReadBudget $budget): int
    {
        $count = $table->uint16($at);
        $at += 2;
        if ($count < $implicit) {
            throw new MalformedFontException('A contextual input sequence cannot be empty.');
        }
        $this->contextArray($table, $at, $count - $implicit, $glyphCount, $budget);
        $at += 2 * ($count - $implicit);

        return $count;
    }

    private function contextArray(BinaryView $table, int $at, int $count, ?int $glyphCount, LayoutReadBudget $budget): void
    {
        $this->records($table, $at, $count, 2, $budget);
        if ($glyphCount !== null) {
            for ($i = 0; $i < $count; ++$i) {
                $this->glyph($table->uint16($at + 2 * $i), $glyphCount);
            }
        }
    }

    private function lookupRecords(BinaryView $table, int $offset, int $count, int $inputCount, int $lookupCount, LayoutReadBudget $budget): void
    {
        $this->records($table, $offset, $count, 4, $budget);
        for ($i = 0; $i < $count; ++$i) {
            // Later indices address the sequence after preceding nested substitutions,
            // which can expand it; their dynamic bounds require contextual execution.
            if (($i === 0 && $table->uint16($offset + 4 * $i) >= $inputCount) || $table->uint16($offset + 4 * $i + 2) >= $lookupCount) {
                throw new MalformedFontException('A contextual lookup record refers outside its input or lookup list.');
            }
        }
    }

    private function classDefinition(BinaryView $table, int $glyphCount, LayoutReadBudget $budget): void
    {
        $format = $table->uint16(0);
        if ($format === 1) {
            $start = $table->uint16(2);
            $count = $table->uint16(4);
            $this->records($table, 6, $count, 2, $budget);
            if ($start + $count > $glyphCount) {
                throw new MalformedFontException('A class definition exceeds maxp.numGlyphs.');
            }

            return;
        }
        if ($format !== 2) {
            throw new MalformedFontException('Unknown class-definition format.');
        }
        $count = $table->uint16(2);
        $this->records($table, 4, $count, 6, $budget);
        $previousEnd = -1;
        for ($i = 0; $i < $count; ++$i) {
            $start = $table->uint16(4 + 6 * $i);
            $end = $table->uint16(6 + 6 * $i);
            if ($start <= $previousEnd || $start > $end || $end >= $glyphCount) {
                throw new MalformedFontException('Class-definition ranges are unordered, overlapping or outside maxp.numGlyphs.');
            }
            $previousEnd = $end;
        }
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
                $alternateFeature = $this->feature($alternate, $features->featureRecords[$featureIndex]->featureTag, $budget);
                foreach ($alternateFeature->lookupListIndices as $lookupIndex) {
                    if ($lookupIndex >= $lookupCount) {
                        throw new MalformedFontException('A variation feature refers to an absent lookup.');
                    }
                }
            }
        }
    }
}
