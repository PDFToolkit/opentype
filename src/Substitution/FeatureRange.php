<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\Tag;

/** Half-open source-cluster interval; later ranges override earlier ranges of the same feature. */
final readonly class FeatureRange
{
    public function __construct(public Tag $feature, public int $start, public int $end, public bool $enabled = true)
    {
        if ($start < 0 || $end <= $start) {
            throw new \InvalidArgumentException('A feature range requires non-negative start < exclusive end.');
        }
    }
}
