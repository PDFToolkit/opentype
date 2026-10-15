<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class AvarTable
{
    /** @param list<AxisSegmentMap> $segments In original fvar axis order. */
    public function __construct(public array $segments)
    {
        if (!array_is_list($segments)) {
            throw new \InvalidArgumentException('Axis segment maps must preserve fvar order.');
        }
        foreach ($segments as $segment) {
            if (!$segment instanceof AxisSegmentMap) {
                throw new \InvalidArgumentException('Expected immutable axis segment maps.');
            }
        }
    }
}
