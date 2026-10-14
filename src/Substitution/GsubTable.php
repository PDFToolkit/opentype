<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\LangSys;
use PDFToolkit\OpenType\Layout\LookupList;
use PDFToolkit\OpenType\Layout\ScriptList;

final readonly class GsubTable
{
    public function __construct(
        public ScriptList $scriptList,
        public FeatureList $featureList,
        public LookupList $lookupList,
        public int $glyphCount,
        public int $minorVersion = 0,
        public ?BinaryView $featureVariations = null,
    ) {
        if ($glyphCount < 1 || $glyphCount > 65535 || !in_array($minorVersion, [0, 1], true) || ($minorVersion === 0 && $featureVariations !== null)) {
            throw new MalformedFontException('Invalid GSUB version or glyph count.');
        }
        foreach ($scriptList->scriptRecords as $record) {
            if ($record->script->defaultLangSys !== null) {
                $this->validateLanguage($record->script->defaultLangSys);
            }
            foreach ($record->script->langSysRecords as $language) {
                $this->validateLanguage($language->langSys);
            }
        }
        foreach ($lookupList->lookups as $lookup) {
            foreach ($lookup->subtables as $subtable) {
                $this->validateSubtable($subtable);
            }
        }
        foreach ($featureList->featureRecords as $record) {
            foreach ($record->feature->lookupListIndices as $index) {
                if ($index >= count($lookupList->lookups)) {
                    throw new MalformedFontException('Feature refers to an absent lookup.');
                }
            }
        }
    }


    private function validateSubtable(SubstitutionSubtable $subtable): void
    {
        if ($subtable instanceof ExtensionSubstFormat1) {
            $this->validateSubtable($subtable->extension);

            return;
        }
        if ($subtable instanceof SingleSubstFormat1 || $subtable instanceof SingleSubstFormat2 || $subtable instanceof MultipleSubstFormat1 || $subtable instanceof AlternateSubstFormat1 || $subtable instanceof LigatureSubstFormat1) {
            foreach ($subtable->coverage->glyphs() as $glyph) {
                $this->validateGlyph($glyph->value);
                if ($subtable instanceof SingleSubstFormat1) {
                    $this->validateGlyph(($glyph->value + $subtable->deltaGlyphId) & 0xFFFF);
                }
            }
        }
        if ($subtable instanceof SingleSubstFormat2) {
            foreach ($subtable->substituteGlyphIds as $glyph) {
                $this->validateGlyph($glyph->value);
            }
        } elseif ($subtable instanceof MultipleSubstFormat1) {
            foreach ($subtable->sequences as $sequence) {
                foreach ($sequence->substituteGlyphIds as $glyph) {
                    $this->validateGlyph($glyph->value);
                }
            }
        } elseif ($subtable instanceof AlternateSubstFormat1) {
            foreach ($subtable->alternateSets as $set) {
                foreach ($set->alternateGlyphIds as $glyph) {
                    $this->validateGlyph($glyph->value);
                }
            }
        } elseif ($subtable instanceof LigatureSubstFormat1) {
            foreach ($subtable->ligatureSets as $set) {
                foreach ($set->ligatures as $ligature) {
                    $this->validateGlyph($ligature->ligatureGlyph->value);
                    foreach ($ligature->componentGlyphIds as $glyph) {
                        $this->validateGlyph($glyph->value);
                    }
                }
            }
        }
    }

    private function validateGlyph(int $value): void
    {
        if ($value >= $this->glyphCount) {
            throw new MalformedFontException('A substitution object refers outside the font glyph set.');
        }
    }

    private function validateLanguage(LangSys $language): void
    {
        $count = count($this->featureList->featureRecords);
        if ($language->requiredFeatureIndex !== null && $language->requiredFeatureIndex >= $count) {
            throw new MalformedFontException('Language refers to an absent required feature.');
        }
        foreach ($language->featureIndices as $index) {
            if ($index >= $count) {
                throw new MalformedFontException('Language refers to an absent feature.');
            }
        }
    }
}
