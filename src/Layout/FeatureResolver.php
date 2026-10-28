<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;

final readonly class FeatureResolver
{
    /** @return list<int> */
    public function resolve(ScriptList $scripts, FeatureList $features, FeatureSelection $selection, LayoutReadBudget $budget, \PDFToolkit\OpenType\Variation\FeatureVariations|\PDFToolkit\OpenType\Binary\BinaryView|null $variations = null): array
    {
        return array_map(static fn (LookupActivation $activation): int => $activation->lookupIndex, $this->resolveActivations($scripts, $features, $selection, $budget, $variations));
    }

    /** @return list<LookupActivation> */
    public function resolveActivations(ScriptList $scripts, FeatureList $features, FeatureSelection $selection, LayoutReadBudget $budget, \PDFToolkit\OpenType\Variation\FeatureVariations|\PDFToolkit\OpenType\Binary\BinaryView|null $variations = null): array
    {
        $script = $scripts->script($selection->script);
        if ($script === null && $selection->fallbackToDefaultScript) {
            $script = $scripts->script(new Tag('DFLT'));
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
            $budget->consume(1);
            $requested[$tag->value] = true;
        }
        $ranges = [];
        foreach ($selection->ranges as $range) {
            $budget->consume(1);
            $ranges[$range->feature->value][] = $range;
        }
        $selected = $language->requiredFeatureIndex === null ? [] : [$language->requiredFeatureIndex => true];
        foreach ($language->featureIndices as $index) {
            $budget->consume(1);
            if (isset($requested[$features->featureRecords[$index]->featureTag->value]) || isset($ranges[$features->featureRecords[$index]->featureTag->value])) {
                $selected[$index] = true;
            }
        }
        $lookups = [];
        if ($selected !== [] && $variations !== null) {
            if (!$variations instanceof \PDFToolkit\OpenType\Variation\FeatureVariations || $selection->coordinates === null) {
                throw new \PDFToolkit\OpenType\Exception\UnsupportedFontException('FeatureVariations require parsed conditions and explicit normalized coordinates.');
            }
            $features = $variations->evaluate($features, $selection->coordinates, $budget);
        }
        foreach ($selected as $index => $_) {
            $tag = $features->featureRecords[$index]->featureTag->value;
            $mask = $index === $language->requiredFeatureIndex ? new FeatureMask(true)
                : new FeatureMask(isset($requested[$tag]), $ranges[$tag] ?? []);
            foreach ($features->featureRecords[$index]->feature->lookupListIndices as $lookup) {
                $budget->consume(1);
                $lookups[$lookup][] = $mask;
            }
        }
        ksort($lookups, SORT_NUMERIC);
        $result = [];
        foreach ($lookups as $index => $masks) {
            $result[] = new LookupActivation($index, $masks);
        }
        return $result;
    }
}
