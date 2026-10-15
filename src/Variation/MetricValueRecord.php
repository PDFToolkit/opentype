<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Positioning\VariationIndex;
use PDFToolkit\OpenType\Value\Tag;

final readonly class MetricValueRecord
{
    public function __construct(public Tag $tag, public VariationIndex $index)
    {
    }
}
