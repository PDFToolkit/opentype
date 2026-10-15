<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;

/** Shared offset/count validation for GDEF, GSUB and GPOS; each read owns its budget. */
final readonly class LayoutBinaryReader
{
    public function child(BinaryView $parent, int $offset, int $minimum): BinaryView
    {
        if ($offset < $minimum || $offset >= $parent->size()) {
            throw new MalformedFontException('A required layout offset is null, inside its header or outside its table.');
        }
        return $parent->view($offset);
    }

    public function records(BinaryView $table, int $at, int $count, int $width, LayoutReadBudget $budget): void
    {
        $budget->consume($count);
        $table->bytes($at, $count * $width);
    }

    public function glyph(int $value, int $glyphCount): GlyphId
    {
        if ($value < 0 || $value >= $glyphCount) {
            throw new MalformedFontException('A layout glyph is outside maxp.numGlyphs.');
        }
        return new GlyphId($value);
    }

    public function coverage(BinaryView $table, int $glyphCount, LayoutReadBudget $budget): Coverage
    {
        $format = $table->uint16(0);
        $count = $table->uint16(2);
        if ($format === 1) {
            $this->records($table, 4, $count, 2, $budget);
            $glyphs = [];
            for ($i = 0; $i < $count; ++$i) {
                $glyphs[] = $this->glyph($table->uint16(4 + 2 * $i), $glyphCount);
            }
            return new CoverageFormat1($glyphs);
        }
        if ($format !== 2) {
            throw new MalformedFontException('Unknown coverage format.');
        }
        $this->records($table, 4, $count, 6, $budget);
        $ranges = [];
        for ($i = 0; $i < $count; ++$i) {
            $range = new RangeRecord($this->glyph($table->uint16(4 + 6 * $i), $glyphCount), $this->glyph($table->uint16(6 + 6 * $i), $glyphCount), $table->uint16(8 + 6 * $i));
            $budget->consume($range->count());
            $ranges[] = $range;
        }
        return new CoverageFormat2($ranges);
    }

    public function classes(BinaryView $table, int $glyphCount, LayoutReadBudget $budget): ClassDefinition
    {
        $format = $table->uint16(0);
        $ranges = [];
        if ($format === 1) {
            $start = $table->uint16(2);
            $count = $table->uint16(4);
            $this->records($table, 6, $count, 2, $budget);
            if ($start + $count > $glyphCount) {
                throw new MalformedFontException('A class definition exceeds the glyph set.');
            }
            for ($i = 0; $i < $count; ++$i) {
                $ranges[] = new ClassRange($start + $i, $start + $i, $table->uint16(6 + 2 * $i));
            }
        } elseif ($format === 2) {
            $count = $table->uint16(2);
            $this->records($table, 4, $count, 6, $budget);
            for ($i = 0; $i < $count; ++$i) {
                $end = $table->uint16(6 + 6 * $i);
                $this->glyph($end, $glyphCount);
                $ranges[] = new ClassRange($table->uint16(4 + 6 * $i), $end, $table->uint16(8 + 6 * $i));
            }
        } else {
            throw new MalformedFontException('Unknown class-definition format.');
        }
        return new ClassDefinition($ranges);
    }

    /** @return list<int> */
    private function values(BinaryView $table, int &$at, int $count, LayoutReadBudget $budget): array
    {
        $this->records($table, $at, $count, 2, $budget);
        $values = [];
        for ($i = 0; $i < $count; ++$i) {
            $values[] = $table->uint16($at + 2 * $i);
        }
        $at += 2 * $count;
        return $values;
    }

    /** @return list<SequenceLookupRecord> */
    private function actions(BinaryView $table, int $at, int $count, int $lookupCount, LayoutReadBudget $budget, int $inputCount): array
    {
        $this->records($table, $at, $count, 4, $budget);
        $actions = [];
        for ($i = 0; $i < $count; ++$i) {
            $index = $table->uint16($at + 4 * $i + 2);
            if ($index >= $lookupCount || ($i === 0 && $table->uint16($at) >= $inputCount)) {
                throw new MalformedFontException('A contextual action refers to an absent lookup.');
            }
            $actions[] = new SequenceLookupRecord($table->uint16($at + 4 * $i), $index);
        }
        return $actions;
    }

    /**
     * @param list<int> $values
     * @return list<GlyphPredicate>
     */
    private function predicates(array $values, ?ClassDefinition $definition, int $glyphCount): array
    {
        return array_map(fn (int $value): GlyphPredicate => $definition === null
            ? new CoveredGlyph(new CoverageFormat1([$this->glyph($value, $glyphCount)]))
            : new ClassGlyph($definition, $value), $values);
    }

    /**
     * @param list<int> $offsets
     * @return list<GlyphPredicate>
     */
    public function coveragePredicates(BinaryView $table, array $offsets, int $header, int $glyphCount, LayoutReadBudget $budget): array
    {
        $predicates = [];
        foreach ($offsets as $offset) {
            $predicates[] = new CoveredGlyph($this->coverage($this->child($table, $offset, $header), $glyphCount, $budget));
        }
        return $predicates;
    }

    /** @return array{Coverage, list<ContextRule>} */
    public function context(BinaryView $table, bool $chained, int $glyphCount, int $lookupCount, LayoutReadBudget $budget): array
    {
        $format = $table->uint16(0);
        if ($format < 1 || $format > 3) {
            throw new MalformedFontException('Unknown contextual layout format.');
        }
        if ($format === 3) {
            $at = 2;
            $backtrack = [];
            $lookahead = [];
            if ($chained) {
                $at = 4;
                $backtrack = $this->values($table, $at, $table->uint16(2), $budget);
                $count = $table->uint16($at);
                $at += 2;
                $input = $this->values($table, $at, $count, $budget);
                $count = $table->uint16($at);
                $at += 2;
                $lookahead = $this->values($table, $at, $count, $budget);
                $count = $table->uint16($at);
                $at += 2;
            } else {
                $inputCount = $table->uint16(2);
                $count = $table->uint16(4);
                $at = 6;
                $input = $this->values($table, $at, $inputCount, $budget);
            }
            if ($input === []) {
                throw new MalformedFontException('A context input cannot be empty.');
            }
            $header = $at + 4 * $count;
            $first = $this->coverage($this->child($table, $input[0], $header), $glyphCount, $budget);
            /** @var non-empty-list<GlyphPredicate> $inputPredicates */
            $inputPredicates = $this->coveragePredicates($table, $input, $header, $glyphCount, $budget);
            return [$first, [new ContextRule($inputPredicates, $this->actions($table, $at, $count, $lookupCount, $budget, count($input)), $this->coveragePredicates($table, $backtrack, $header, $glyphCount, $budget), $this->coveragePredicates($table, $lookahead, $header, $glyphCount, $budget))]];
        }
        $countAt = $format === 1 ? 4 : ($chained ? 10 : 6);
        $count = $table->uint16($countAt);
        $at = $countAt + 2;
        $offsets = $this->values($table, $at, $count, $budget);
        $coverage = $this->coverage($this->child($table, $table->uint16(2), $at), $glyphCount, $budget);
        if ($format === 1 && $coverage->count() !== $count) {
            throw new MalformedFontException('Context coverage and rule-set counts differ.');
        }
        $backDefinition = $inputDefinition = $lookDefinition = null;
        if ($format === 2) {
            $inputDefinition = $this->classes($this->child($table, $table->uint16($chained ? 6 : 4), $at), $glyphCount, $budget);
            if ($chained) {
                $backDefinition = $this->classes($this->child($table, $table->uint16(4), $at), $glyphCount, $budget);
                $lookDefinition = $this->classes($this->child($table, $table->uint16(8), $at), $glyphCount, $budget);
            }
        }
        $rules = [];
        $covered = $coverage->glyphs();
        foreach ($offsets as $index => $offset) {
            if ($offset === 0) {
                continue;
            }
            $set = $this->child($table, $offset, $at);
            $ruleCount = $set->uint16(0);
            $ruleAt = 2;
            $ruleOffsets = $this->values($set, $ruleAt, $ruleCount, $budget);
            foreach ($ruleOffsets as $ruleOffset) {
                $rule = $this->child($set, $ruleOffset, $ruleAt);
                $cursor = 0;
                $back = $look = [];
                if ($chained) {
                    $n = $rule->uint16($cursor);
                    $cursor += 2;
                    $back = $this->values($rule, $cursor, $n, $budget);
                    $n = $rule->uint16($cursor);
                    $cursor += 2;
                    if ($n < 1) {
                        throw new MalformedFontException('A context input cannot be empty.');
                    }
                    $input = $this->values($rule, $cursor, $n - 1, $budget);
                    $n = $rule->uint16($cursor);
                    $cursor += 2;
                    $look = $this->values($rule, $cursor, $n, $budget);
                    $actionCount = $rule->uint16($cursor);
                    $cursor += 2;
                } else {
                    $n = $rule->uint16(0);
                    $actionCount = $rule->uint16(2);
                    $cursor = 4;
                    if ($n < 1) {
                        throw new MalformedFontException('A context input cannot be empty.');
                    }
                    $input = $this->values($rule, $cursor, $n - 1, $budget);
                }
                array_unshift($input, $format === 1 ? $covered[$index]->value : $index);
                /** @var non-empty-list<GlyphPredicate> $inputPredicates */
                $inputPredicates = $this->predicates($input, $inputDefinition, $glyphCount);
                $rules[] = new ContextRule($inputPredicates, $this->actions($rule, $cursor, $actionCount, $lookupCount, $budget, count($input)), $this->predicates($back, $backDefinition, $glyphCount), $this->predicates($look, $lookDefinition, $glyphCount));
            }
        }
        return [$coverage, $rules];
    }
}
