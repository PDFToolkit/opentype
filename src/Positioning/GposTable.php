<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Layout\FeatureList;
use PDFToolkit\OpenType\Layout\ScriptList;

final readonly class GposTable
{
    /** @param list<PositioningLookup> $lookups */
    public function __construct(public ScriptList $scriptList, public FeatureList $featureList, public array $lookups, public int $glyphCount, public ?BinaryView $featureVariations = null)
    {
        if ($glyphCount < 1 || $glyphCount > 65535) {
            throw new MalformedFontException('Invalid positioning glyph count.');
        }
        foreach ($featureList->featureRecords as $record) {
            foreach ($record->feature->lookupListIndices as $index) {
                if (!isset($lookups[$index])) {
                    throw new MalformedFontException('A positioning feature refers to an absent lookup.');
                }
            }
        }
        foreach ($scriptList->scriptRecords as $record) {
            $languages = array_map(fn ($item) => $item->langSys, $record->script->langSysRecords);
            if ($record->script->defaultLangSys !== null) {
                $languages[] = $record->script->defaultLangSys;
            }
            foreach ($languages as $language) {
                foreach ([...$language->featureIndices, ...($language->requiredFeatureIndex === null ? [] : [$language->requiredFeatureIndex])] as $index) {
                    if (!isset($featureList->featureRecords[$index])) {
                        throw new MalformedFontException('A positioning language refers to an absent feature.');
                    }
                }
            }
        }
    }
}
