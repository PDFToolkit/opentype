<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\CodePoint;

final readonly class CharacterVariantParameters implements FeatureParameters
{
    /** @param list<CodePoint> $characters */
    public function __construct(
        public int $uiLabelNameId,
        public int $uiTooltipNameId,
        public int $sampleTextNameId,
        public int $namedParameterCount,
        public int $firstParameterNameId,
        public array $characters,
    ) {
        foreach ([$uiLabelNameId, $uiTooltipNameId, $sampleTextNameId, $namedParameterCount, $firstParameterNameId] as $value) {
            if ($value < 0 || $value > 65535) {
                throw new \InvalidArgumentException('Character-variant fields are unsigned 16-bit values.');
            }
        }
        if (!array_is_list($characters) || ($namedParameterCount === 0 && $firstParameterNameId !== 0)
            || ($namedParameterCount > 0 && $firstParameterNameId + $namedParameterCount - 1 > 32767)
        ) {
            throw new \InvalidArgumentException('Invalid character-variant names or character list.');
        }
        foreach ($characters as $character) {
            if (!$character instanceof CodePoint) {
                throw new \InvalidArgumentException('Character variants contain explicit Unicode scalar objects.');
            }
        }
    }

    /** Feature parameter values and alternate choices are one-based. */
    public function parameterNameId(int $parameter): ?int
    {
        return $parameter < 1 || $parameter > $this->namedParameterCount ? null : $this->firstParameterNameId + $parameter - 1;
    }
}
