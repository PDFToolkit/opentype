<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\Lookup;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

/** One explicitly selected feature stage in logical glyph order, not a Unicode shaping engine. */
final readonly class SubstitutionEngine
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function apply(GsubTable $table, FeatureSelection $selection, GlyphSequence $input): GlyphSequence
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
        $lookups = $this->selectedLookups($table, $selection, $operations);
        $records = new LayoutReadBudget($this->limits->maxLayoutRecords);
        // Validate every selected lookup before applying any operation.
        foreach ($lookups as $lookup) {
            $records->consume(1 + count($lookup->subtables));
            if ($lookup->lookupFlags->value !== 0) {
                throw new UnsupportedFontException('Only zero lookup flags are executable; GDEF filtering is not implemented and RIGHT_TO_LEFT is not a GSUB direction selector.');
            }
            foreach ($lookup->subtables as $subtable) {
                $this->preflight($subtable, $selection, $records);
            }
        }
        $output = $input;
        foreach ($lookups as $lookup) {
            $output = $this->applyLookup($lookup, $selection, $output, $operations);
        }

        return $output;
    }

    /** @return list<Lookup> */
    private function selectedLookups(GsubTable $table, FeatureSelection $selection, LayoutReadBudget $operations): array
    {
        $script = $table->scriptList->script($selection->script);
        if ($script === null && $selection->fallbackToDefaultScript) {
            $script = $table->scriptList->script(new Tag('DFLT'));
        }
        if ($script === null) {
            return [];
        }
        $language = $selection->language === null ? $script->defaultLangSys : $script->language($selection->language);
        if ($language === null && $selection->fallbackToDefaultLanguage) {
            $language = $script->defaultLangSys;
        }
        if ($language === null) {
            return [];
        }
        $requested = [];
        foreach ($selection->features as $tag) {
            $operations->consume(1);
            $requested[$tag->value] = true;
        }
        $indices = $language->requiredFeatureIndex === null ? [] : [$language->requiredFeatureIndex => true];
        foreach ($language->featureIndices as $index) {
            $operations->consume(1);
            if (isset($requested[$table->featureList->featureRecords[$index]->featureTag->value])) {
                $indices[$index] = true;
            }
        }
        if ($indices !== [] && $table->featureVariations !== null) {
            throw new UnsupportedFontException('FeatureVariations evaluation requires an explicit variation model and is not implemented.');
        }
        $lookupIndices = [];
        foreach ($indices as $index => $_) {
            foreach ($table->featureList->featureRecords[$index]->feature->lookupListIndices as $lookupIndex) {
                $operations->consume(1);
                $lookupIndices[$lookupIndex] = true;
            }
        }
        ksort($lookupIndices, SORT_NUMERIC);
        $lookups = [];
        foreach ($lookupIndices as $index => $_) {
            $lookups[] = $table->lookupList->lookups[$index];
        }

        return $lookups;
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
        throw new UnsupportedFontException('The selected substitution subtable has no preflight implementation.');
    }

    private function applyLookup(Lookup $lookup, FeatureSelection $selection, GlyphSequence $input, LayoutReadBudget $operations): GlyphSequence
    {
        $glyphs = [];
        $clusters = [];
        $inputCount = count($input->glyphs);
        for ($position = 0; $position < $inputCount;) {
            $match = null;
            foreach ($lookup->subtables as $subtable) {
                $operations->consume(1);
                $match = $this->match($subtable, $selection, $input, $position, $operations);
                if ($match !== null) {
                    break;
                }
            }
            if ($match === null) {
                $this->append($glyphs, $clusters, [$input->glyphs[$position]], $input->clusters[$position]);
                ++$position;
            } else {
                // A ligature carries the earliest cluster of all consumed input glyphs.
                $cluster = $input->clusters[$position];
                for ($offset = 1; $offset < $match->consumed; ++$offset) {
                    $cluster = min($cluster, $input->clusters[$position + $offset]);
                }
                $this->append($glyphs, $clusters, $match->replacement, $cluster);
                $position += $match->consumed;
            }
        }

        return new GlyphSequence($glyphs, $clusters);
    }

    private function match(SubstitutionSubtable $subtable, FeatureSelection $selection, GlyphSequence $input, int $position, LayoutReadBudget $operations): ?SubstitutionMatch
    {
        if ($subtable instanceof ExtensionSubstFormat1) {
            return $this->match($subtable->extension, $selection, $input, $position, $operations);
        }
        $glyph = $input->glyphs[$position];
        if ($subtable instanceof SingleSubstFormat1 || $subtable instanceof SingleSubstFormat2) {
            $substitute = $subtable->substitute($glyph);

            return $substitute === null ? null : new SubstitutionMatch(1, [$substitute]);
        }
        if ($subtable instanceof MultipleSubstFormat1) {
            $index = $subtable->coverage->indexOf($glyph);

            return $index === null ? null : new SubstitutionMatch(1, $subtable->sequences[$index]->substituteGlyphIds);
        }
        if ($subtable instanceof AlternateSubstFormat1) {
            $index = $subtable->coverage->indexOf($glyph);
            if ($index === null) {
                return null;
            }
            $set = $subtable->alternateSets[$index];
            if ($set->alternateGlyphIds === []) {
                return null;
            }
            $selector = $selection->alternateSelector ?? throw new UnsupportedFontException('An alternate selector is required.');
            $substitute = $selector->select($glyph, $set);
            foreach ($set->alternateGlyphIds as $alternate) {
                $operations->consume(1);
                if ($alternate->value === $substitute->value) {
                    return new SubstitutionMatch(1, [$substitute]);
                }
            }
            throw new \InvalidArgumentException('AlternateSelector returned a glyph outside the alternate set.');
        }
        if ($subtable instanceof LigatureSubstFormat1) {
            $index = $subtable->coverage->indexOf($glyph);
            if ($index === null) {
                return null;
            }
            foreach ($subtable->ligatureSets[$index]->ligatures as $ligature) {
                $operations->consume(1);
                $matched = true;
                foreach ($ligature->componentGlyphIds as $offset => $component) {
                    $operations->consume(1);
                    if (!isset($input->glyphs[$position + $offset + 1]) || $input->glyphs[$position + $offset + 1]->value !== $component->value) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    return new SubstitutionMatch(1 + count($ligature->componentGlyphIds), [$ligature->ligatureGlyph]);
                }
            }

            return null;
        }
        throw new UnsupportedFontException('The selected substitution subtable has no execution implementation.');
    }

    /**
     * @param list<GlyphId> $glyphs
     * @param list<int> $clusters
     * @param non-empty-list<GlyphId> $replacement
     */
    private function append(array &$glyphs, array &$clusters, array $replacement, int $cluster): void
    {
        if (count($replacement) > $this->limits->maxOutputGlyphs - count($glyphs)) {
            throw new MalformedFontException('Substitution expansion exceeds the output glyph limit.');
        }
        foreach ($replacement as $glyph) {
            $glyphs[] = $glyph;
            $clusters[] = $cluster;
        }
    }
}
