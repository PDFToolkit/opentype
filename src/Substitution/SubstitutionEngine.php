<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\Lookup;
use PDFToolkit\OpenType\Layout\GdefTable;
use PDFToolkit\OpenType\Layout\ContextMatcher;
use PDFToolkit\OpenType\Layout\FeatureResolver;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

/** One explicitly selected feature stage in logical glyph order, not a Unicode shaping engine. */
final readonly class SubstitutionEngine
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function apply(GsubTable $table, FeatureSelection $selection, GlyphSequence $input, ?GdefTable $definition = null): GlyphSequence
    {
        if (count($input->glyphs) > $this->limits->maxOutputGlyphs || $table->glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('The input glyph sequence exceeds the configured limits.');
        }
        foreach ($input->glyphs as $glyph) {
            if ($glyph->value >= $table->glyphCount) {
                throw new MalformedFontException('An input glyph is outside the font glyph set.');
            }
        }
        $operations = new LayoutReadBudget($this->limits->maxSubstitutionOperations);
        $indices = (new FeatureResolver())->resolve($table->scriptList, $table->featureList, $selection, $operations, $table->featureVariations !== null);
        $definition ??= new GdefTable();
        $records = new LayoutReadBudget($this->limits->maxLayoutRecords);
        $visited = [];
        foreach ($indices as $index) {
            $this->preflightLookup($table, $index, $selection, $definition, $records, $visited);
        }
        $buffer = new GlyphBuffer($input, $this->limits->maxOutputGlyphs);
        foreach ($indices as $index) {
            $lookup = $table->lookupList->lookups[$index];
            $reverse = $lookup->lookupType === 8 || ($lookup->subtables[0] instanceof ExtensionSubstFormat1 && $lookup->subtables[0]->extensionLookupType === 8);
            if ($reverse) {
                for ($position = count($buffer->glyphs) - 1; $position >= 0; --$position) {
                    $this->applyAt($table, $index, $selection, $buffer, $position, $definition, $operations, 0);
                }
            } else {
                for ($position = 0; $position < count($buffer->glyphs);) {
                    $next = $this->applyAt($table, $index, $selection, $buffer, $position, $definition, $operations, 0);
                    $position = $next ?? $position + 1;
                }
            }
        }
        return $buffer->result();
    }

    /** @param array<int, true> $visited */
    private function preflightLookup(GsubTable $table, int $index, FeatureSelection $selection, GdefTable $definition, LayoutReadBudget $records, array &$visited): void
    {
        if (isset($visited[$index])) {
            return;
        }
        $visited[$index] = true;
        $lookup = $table->lookupList->lookups[$index] ?? throw new MalformedFontException('A context action refers to an absent lookup.');
        $records->consume(1 + count($lookup->subtables));
        if ($lookup->lookupFlags->markFilteringSet !== null && !isset($definition->markGlyphSets[$lookup->lookupFlags->markFilteringSet])) {
            throw new MalformedFontException('A lookup refers to an absent GDEF mark filtering set.');
        }
        foreach ($lookup->subtables as $subtable) {
            $this->preflight($subtable, $selection, $records);
            $concrete = $subtable instanceof ExtensionSubstFormat1 ? $subtable->extension : $subtable;
            if ($concrete instanceof ContextSubstitution) {
                foreach ($concrete->rules as $rule) {
                    foreach ($rule->lookupRecords as $record) {
                        $this->preflightLookup($table, $record->lookupListIndex, $selection, $definition, $records, $visited);
                    }
                }
            }
        }
    }

    private function preflight(SubstitutionSubtable $subtable, FeatureSelection $selection, LayoutReadBudget $records): void
    {
        if (!$subtable->isSupported()) {
            throw new UnsupportedFontException('Selected GSUB lookup type '.$subtable->lookupType().' is not implemented.');
        }
        if ($subtable instanceof ExtensionSubstFormat1) {
            $this->preflight($subtable->extension, $selection, $records);

            return;
        }
        if ($subtable instanceof SingleSubstFormat1 || $subtable instanceof SingleSubstFormat2) {
            $records->consume($subtable->coverage->count());

            return;
        }
        if ($subtable instanceof AlternateSubstFormat1) {
            if ($selection->alternateSelector === null) {
                throw new UnsupportedFontException('Alternate substitutions require an explicit AlternateSelector.');
            }
            $records->consume($subtable->coverage->count());
            foreach ($subtable->alternateSets as $set) {
                $records->consume(count($set->alternateGlyphIds));
            }

            return;
        }
        if ($subtable instanceof MultipleSubstFormat1) {
            $records->consume($subtable->coverage->count());
            foreach ($subtable->sequences as $sequence) {
                $count = count($sequence->substituteGlyphIds);
                $records->consume($count);
                if ($count > $this->limits->maxOutputGlyphs) {
                    throw new MalformedFontException('A substitution sequence exceeds the output glyph limit.');
                }
            }

            return;
        }
        if ($subtable instanceof LigatureSubstFormat1) {
            $records->consume($subtable->coverage->count());
            foreach ($subtable->ligatureSets as $set) {
                $records->consume(count($set->ligatures));
                foreach ($set->ligatures as $ligature) {
                    $records->consume(count($ligature->componentGlyphIds));
                }
            }

            return;
        }
        if ($subtable instanceof ContextSubstitution) {
            $records->consume($subtable->coverage->count());
            foreach ($subtable->rules as $rule) {
                $records->consume(count($rule->input) + count($rule->backtrack) + count($rule->lookahead) + count($rule->lookupRecords));
            }
            return;
        }
        if ($subtable instanceof ReverseChainSingleSubstitution) {
            $records->consume($subtable->coverage->count() + count($subtable->context->backtrack) + count($subtable->context->lookahead));
            return;
        }
        throw new UnsupportedFontException('The selected substitution subtable has no preflight implementation.');
    }

    private function applyAt(GsubTable $table, int $lookupIndex, FeatureSelection $selection, GlyphBuffer $buffer, int $position, GdefTable $definition, LayoutReadBudget $operations, int $depth): ?int
    {
        $operations->consume(1);
        if ($depth >= $this->limits->maxLookupNesting) {
            throw new MalformedFontException('Nested contextual lookups exceed the configured depth.');
        }
        $lookup = $table->lookupList->lookups[$lookupIndex];
        if (!isset($buffer->glyphs[$position]) || $definition->ignores($buffer->glyphs[$position], $lookup->lookupFlags)) {
            return null;
        }
        foreach ($lookup->subtables as $subtable) {
            $operations->consume(1);
            $subtable = $subtable instanceof ExtensionSubstFormat1 ? $subtable->extension : $subtable;
            $glyph = $buffer->glyphs[$position];
            if ($subtable instanceof ContextSubstitution) {
                if ($subtable->coverage->indexOf($glyph) === null) {
                    continue;
                }
                foreach ($subtable->rules as $rule) {
                    $matched = (new ContextMatcher())->match($rule, $buffer->glyphs, $position, $lookup->lookupFlags, $definition, $operations);
                    if ($matched === null) {
                        continue;
                    }
                    $end = $matched[count($matched) - 1] + 1;
                    foreach ($rule->lookupRecords as $record) {
                        $cursor = $position;
                        $target = $position;
                        for ($sequence = 0; $sequence <= $record->sequenceIndex; ++$sequence) {
                            $target = (new ContextMatcher())->next($buffer->glyphs, $cursor, 1, $lookup->lookupFlags, $definition, $operations);
                            if ($target === null || $target >= $end) {
                                throw new MalformedFontException('A contextual action index exceeds the current matched input.');
                            }
                            $cursor = $target + 1;
                        }
                        $before = count($buffer->glyphs);
                        $this->applyAt($table, $record->lookupListIndex, $selection, $buffer, $target, $definition, $operations, $depth + 1);
                        $end += count($buffer->glyphs) - $before;
                    }
                    return $end > $position ? $end : $position + 1;
                }
                continue;
            }
            if ($subtable instanceof ReverseChainSingleSubstitution) {
                $index = $subtable->coverage->indexOf($glyph);
                if ($index !== null && (new ContextMatcher())->match($subtable->context, $buffer->glyphs, $position, $lookup->lookupFlags, $definition, $operations) !== null) {
                    $buffer->replace([$position], [$subtable->substitutes[$index]]);
                    return $position + 1;
                }
                continue;
            }
            if ($subtable instanceof SingleSubstFormat1 || $subtable instanceof SingleSubstFormat2) {
                $substitute = $subtable->substitute($glyph);
                if ($substitute !== null) {
                    $buffer->replace([$position], [$substitute]);
                    return $position + 1;
                }
                continue;
            }
            if ($subtable instanceof MultipleSubstFormat1) {
                $index = $subtable->coverage->indexOf($glyph);
                if ($index !== null) {
                    $replacement = $subtable->sequences[$index]->substituteGlyphIds;
                    $buffer->replace([$position], $replacement);
                    return $position + count($replacement);
                }
                continue;
            }
            if ($subtable instanceof AlternateSubstFormat1) {
                $index = $subtable->coverage->indexOf($glyph);
                if ($index === null || $subtable->alternateSets[$index]->alternateGlyphIds === []) {
                    continue;
                }
                $set = $subtable->alternateSets[$index];
                $selector = $selection->alternateSelector ?? throw new UnsupportedFontException('An alternate selector is required.');
                $substitute = $selector->select($glyph, $set);
                $valid = false;
                foreach ($set->alternateGlyphIds as $alternate) {
                    $operations->consume(1);
                    $valid = $valid || $alternate->value === $substitute->value;
                }
                if (!$valid) {
                    throw new \InvalidArgumentException('AlternateSelector returned a glyph outside the alternate set.');
                }
                $buffer->replace([$position], [$substitute]);
                return $position + 1;
            }
            if ($subtable instanceof LigatureSubstFormat1) {
                $index = $subtable->coverage->indexOf($glyph);
                if ($index === null) {
                    continue;
                }
                foreach ($subtable->ligatureSets[$index]->ligatures as $ligature) {
                    $operations->consume(1);
                    $positions = [$position];
                    $cursor = $position + 1;
                    foreach ($ligature->componentGlyphIds as $component) {
                        $cursor = (new ContextMatcher())->next($buffer->glyphs, $cursor, 1, $lookup->lookupFlags, $definition, $operations);
                        if ($cursor === null || $buffer->glyphs[$cursor]->value !== $component->value) {
                            continue 2;
                        }
                        $positions[] = $cursor;
                        ++$cursor;
                    }
                    $buffer->replace($positions, [$ligature->ligatureGlyph]);
                    return $position + 1;
                }
                continue;
            }
            throw new UnsupportedFontException('The selected substitution subtable has no execution implementation.');
        }
        return null;
    }
}
