<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

interface VariationDeltaResolver
{
    public function delta(VariationIndex $index): float;
}
