<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\Tag;

final readonly class FeatureRecord
{
    public function __construct(public Tag $featureTag, public Feature $feature)
    {
    }
}
