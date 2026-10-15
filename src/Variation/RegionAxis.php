<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class RegionAxis
{
    public function __construct(public float $start, public float $peak, public float $end)
    {
        foreach ([$start, $peak, $end] as $value) {
            if (!is_finite($value) || $value < -1 || $value > 1) {
                throw new \InvalidArgumentException('Variation region coordinates must lie in [-1, 1].');
            }
        }
    }

    public function scalar(float $coordinate): float
    {
        // Invalid support triples do not participate in this axis, as required by OpenType.
        if ($this->start > $this->peak || $this->peak > $this->end || ($this->start < 0 && $this->end > 0 && $this->peak !== 0.0) || $this->peak === 0.0) {
            return 1.0;
        }
        if ($coordinate === $this->peak) {
            return 1.0;
        }
        if ($coordinate <= $this->start || $coordinate >= $this->end) {
            return 0.0;
        }
        return $coordinate < $this->peak ? ($coordinate - $this->start) / ($this->peak - $this->start) : ($this->end - $coordinate) / ($this->end - $this->peak);
    }
}
