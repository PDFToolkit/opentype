<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class VariationSelectorRecord
{
    /**
     * @param list<UnicodeRange> $defaultRanges
     * @param list<UvsMapping> $nonDefaultMappings
     */
    public function __construct(
        public CodePoint $varSelector,
        private array $defaultRanges,
        private array $nonDefaultMappings,
    ) {
        $value = $varSelector->value;
        if (!(
            ($value >= 0x180B && $value <= 0x180D) || $value === 0x180F
            || ($value >= 0xFE00 && $value <= 0xFE0F) || ($value >= 0xE0100 && $value <= 0xE01EF)
        )) {
            throw new \InvalidArgumentException('The selector must be a Unicode variation selector.');
        }
        if (!array_is_list($defaultRanges) || !array_is_list($nonDefaultMappings)) {
            throw new \InvalidArgumentException('Variation mapping records require lists.');
        }
        $previous = -1;
        foreach ($defaultRanges as $range) {
            if (!$range instanceof UnicodeRange) {
                throw new \InvalidArgumentException('Default UVS data contains immutable UnicodeRange records.');
            }
            if ($range->startUnicodeValue->value <= $previous) {
                throw new \InvalidArgumentException('Default UVS ranges must be sorted and disjoint.');
            }
            $previous = $range->endUnicodeValue();
        }
        $previous = -1;
        foreach ($nonDefaultMappings as $mapping) {
            if (!$mapping instanceof UvsMapping) {
                throw new \InvalidArgumentException('Non-default UVS data contains immutable UvsMapping records.');
            }
            if ($mapping->unicodeValue->value <= $previous || $this->isDefault($mapping->unicodeValue)) {
                throw new \InvalidArgumentException('Non-default UVS mappings must be sorted, unique, and disjoint from default UVS ranges.');
            }
            $previous = $mapping->unicodeValue->value;
        }
    }

    public function glyphFor(CodePoint $base, CharacterMapSubtable $defaultMap): ?GlyphId
    {
        $low = 0;
        $high = count($this->nonDefaultMappings) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $mapping = $this->nonDefaultMappings[$mid];
            if ($base->value < $mapping->unicodeValue->value) {
                $high = $mid - 1;
            } elseif ($base->value > $mapping->unicodeValue->value) {
                $low = $mid + 1;
            } else {
                return $mapping->glyphId;
            }
        }

        return $this->isDefault($base) ? $defaultMap->glyphFor($base) : null;
    }

    private function isDefault(CodePoint $base): bool
    {
        $low = 0;
        $high = count($this->defaultRanges) - 1;
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            $range = $this->defaultRanges[$mid];
            if ($base->value < $range->startUnicodeValue->value) {
                $high = $mid - 1;
            } elseif ($base->value > $range->endUnicodeValue()) {
                $low = $mid + 1;
            } else {
                return true;
            }
        }

        return false;
    }
}
