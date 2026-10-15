<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

/** Tuple-store support region; an omitted intermediate region saturates beyond its peak. */
final readonly class TupleRegion
{
    public function __construct(public NormalizedCoordinates $peak, public ?NormalizedCoordinates $start = null, public ?NormalizedCoordinates $end = null)
    {
        if (($start === null) !== ($end === null)) {
            throw new \InvalidArgumentException('Intermediate tuple regions require both bounds.');
        }
        if ($start !== null && $end !== null && (count($start->values) !== count($peak->values) || count($end->values) !== count($peak->values))) {
            throw new \InvalidArgumentException('Tuple-region dimensions must match.');
        }
    }

    public function scalar(NormalizedCoordinates $coordinates): float
    {
        if (count($coordinates->values) !== count($this->peak->values)) {
            throw new \InvalidArgumentException('Tuple-region coordinates must match every axis.');
        }
        $scalar = 1.0;
        foreach ($this->peak->values as $axis => $peak) {
            if ($peak == 0) {
                continue;
            }
            $value = $coordinates->values[$axis];
            if ($this->start !== null && $this->end !== null) {
                $start = $this->start->values[$axis];
                $end = $this->end->values[$axis];
                // Invalid intermediate bounds make this axis non-participating.
                if ($start > $peak || $peak > $end || ($start < 0 && $end > 0)) {
                    continue;
                }
                if ($value < $start || $value > $end) {
                    return 0.0;
                }
                if ($value < $peak) {
                    $scalar *= ($value - $start) / ($peak - $start);
                } elseif ($value > $peak) {
                    $scalar *= ($end - $value) / ($end - $peak);
                }
            } else {
                if ($value == 0 || $value * $peak < 0) {
                    return 0.0;
                }
                $scalar *= min(1.0, $value / $peak);
            }
        }

        return $scalar;
    }
}
