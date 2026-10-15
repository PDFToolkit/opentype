<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metrics;

/** OS/2 optical bounds use twentieths of typographic points, inclusive/exclusive. */
final readonly class OpticalPointRange
{
    public function __construct(public int $lower, public int $upper)
    {
        if ($lower < 0 || $upper <= $lower || $upper < 2 || $upper > 65535) {
            throw new \InvalidArgumentException('Invalid optical-size range.');
        }
    }

    public function contains(float $points): bool
    {
        return is_finite($points) && $points * 20 >= $this->lower && ($this->upper === 65535 || $points * 20 < $this->upper);
    }
}
