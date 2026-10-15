<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Positioning\VariationIndex;

/** HVAR or VVAR. Missing side-bearing/origin maps are absent data, not zero deltas. */
final readonly class GlyphMetricVariations
{
    public function __construct(
        public GlyphMetricAxis $axis,
        public int $glyphCount,
        public ItemVariationStore $store,
        public ?DeltaSetIndexMap $advances = null,
        public ?DeltaSetIndexMap $firstBearings = null,
        public ?DeltaSetIndexMap $secondBearings = null,
        public ?DeltaSetIndexMap $verticalOrigins = null,
    ) {
        if ($glyphCount < 1 || $glyphCount > 65535 || ($axis === GlyphMetricAxis::Horizontal && $verticalOrigins !== null)) {
            throw new \InvalidArgumentException('Invalid variable metric dimensions.');
        }
        foreach ([$advances, $firstBearings, $secondBearings, $verticalOrigins] as $mapping) {
            if ($mapping === null) {
                continue;
            }
            if ($mapping->format !== 0) {
                throw new MalformedFontException('HVAR and VVAR require delta-set map format 0.');
            }
            foreach ($mapping->entries as $entry) {
                $this->validateIndex($entry);
            }
        }
        if ($advances === null) {
            // Implicit indices refer to row glyphID of the first data subtable.
            $this->validateIndex(new VariationIndex(0, $glyphCount - 1));
        }
    }

    private function validateIndex(VariationIndex $index): void
    {
        if ($index->outerIndex === 65535 && $index->innerIndex === 65535) {
            return;
        }
        if (!array_key_exists($index->outerIndex, $this->store->data)) {
            throw new MalformedFontException('A metric variation index refers to an absent store subtable.');
        }
        $data = $this->store->data[$index->outerIndex];
        if ($data !== null && !isset($data->deltaSets[$index->innerIndex])) {
            throw new MalformedFontException('A metric variation index refers to an absent delta row.');
        }
    }
}
