<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

interface FeatureCondition
{
    public function matches(NormalizedCoordinates $coordinates): bool;
}
