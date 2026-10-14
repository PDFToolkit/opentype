<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

/** One selected Unicode subtable, optionally supplemented by format 14. */
final readonly class UnicodeCharacterMap
{
    /** @var array<int, VariationSelectorRecord> */
    private array $variationSelectors;

    /** @param list<VariationSelectorRecord> $variationSelectors */
    public function __construct(
        public EncodingRecord $encoding,
        private SegmentedCharacterMap|SequentialCharacterMap $defaultMap,
        array $variationSelectors = [],
    ) {
        if (!array_is_list($variationSelectors)) {
            throw new \InvalidArgumentException('Variation selector records require a list.');
        }
        $selectors = [];
        foreach ($variationSelectors as $selector) {
            if (!$selector instanceof VariationSelectorRecord) {
                throw new \InvalidArgumentException('Variation data contains immutable VariationSelectorRecord objects.');
            }
            if (isset($selectors[$selector->varSelector->value])) {
                throw new \InvalidArgumentException('A Unicode character map cannot contain duplicate variation selectors.');
            }
            $selectors[$selector->varSelector->value] = $selector;
        }
        $this->variationSelectors = $selectors;
    }

    public function format(): int
    {
        return $this->defaultMap->format();
    }

    public function glyphFor(CodePoint $codePoint): GlyphId
    {
        return $this->defaultMap->glyphFor($codePoint);
    }

    /** null means that the font does not support this variation sequence. */
    public function glyphForVariation(CodePoint $base, CodePoint $selector): ?GlyphId
    {
        return isset($this->variationSelectors[$selector->value])
            ? $this->variationSelectors[$selector->value]->glyphFor($base, $this->defaultMap)
            : null;
    }
}
