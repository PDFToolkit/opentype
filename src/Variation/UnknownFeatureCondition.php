<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

/** Future condition formats fail their condition set while later records remain usable. */
final readonly class UnknownFeatureCondition implements FeatureCondition
{
    public function __construct(public int $format)
    {
    }

    public function matches(NormalizedCoordinates $coordinates): bool
    {
        return false;
    }
}
