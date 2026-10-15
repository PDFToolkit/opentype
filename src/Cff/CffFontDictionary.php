<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

final readonly class CffFontDictionary
{
    public function __construct(public CffDictionary $dictionary, public CffDictionary $privateDictionary, public CffIndex $localSubroutines, public float $defaultWidth = 0.0, public float $nominalWidth = 0.0, public int $variationIndex = 0)
    {
        if (!is_finite($defaultWidth) || !is_finite($nominalWidth) || $variationIndex < 0 || $variationIndex > 65535) {
            throw new \InvalidArgumentException('Invalid CFF widths or default variation index.');
        }
    }
}
