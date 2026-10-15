<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class VariationCondition implements FeatureCondition
{
    public function __construct(public int $axisIndex, public float $minimum, public float $maximum)
    {
        if ($axisIndex < 0 || $axisIndex > 65535 || !is_finite($minimum) || !is_finite($maximum) || $minimum < -1 || $maximum > 1 || $minimum > $maximum) {
            throw new \InvalidArgumentException('Invalid feature variation condition.');
        }
    }

    public function matches(NormalizedCoordinates $coordinates): bool
    {
        if (!array_key_exists($this->axisIndex, $coordinates->values)) {
            return false;
        }
        $value = $coordinates->axis($this->axisIndex);
        return $value >= $this->minimum && $value <= $this->maximum;
    }
}
