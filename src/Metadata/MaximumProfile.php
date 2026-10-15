<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

/** Declared maxp limits are metadata, never replacements for application resource budgets. */
final readonly class MaximumProfile
{
    public function __construct(public int $version, public int $numGlyphs, public ?int $maxPoints = null, public ?int $maxContours = null, public ?int $maxCompositePoints = null, public ?int $maxCompositeContours = null, public ?int $maxZones = null, public ?int $maxTwilightPoints = null, public ?int $maxStorage = null, public ?int $maxFunctionDefs = null, public ?int $maxInstructionDefs = null, public ?int $maxStackElements = null, public ?int $maxSizeOfInstructions = null, public ?int $maxComponentElements = null, public ?int $maxComponentDepth = null)
    {
        if (!in_array($version, [0x00005000, 0x00010000], true) || $numGlyphs < 1 || $numGlyphs > 65535) {
            throw new \InvalidArgumentException('Invalid maxp version or glyph count.');
        }
        foreach ([$maxPoints, $maxContours, $maxCompositePoints, $maxCompositeContours, $maxZones, $maxTwilightPoints, $maxStorage, $maxFunctionDefs, $maxInstructionDefs, $maxStackElements, $maxSizeOfInstructions, $maxComponentElements, $maxComponentDepth] as $value) {
            if (($version === 0x00005000 && $value !== null) || ($version === 0x00010000 && ($value === null || $value < 0 || $value > 65535))) {
                throw new \InvalidArgumentException('maxp fields must match their version and uint16 range.');
            }
        }
    }
}
