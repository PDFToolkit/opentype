<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Value\Tag;

/** One fvar axis in the font's original axis order. Values use the user scale. */
final readonly class VariationAxis
{
    public function __construct(
        public Tag $tag,
        public float $minimum,
        public float $default,
        public float $maximum,
        public int $flags,
        public int $nameId,
    ) {
        foreach ([$minimum, $default, $maximum] as $value) {
            if (!is_finite($value) || $value < -32768 || $value > 32767 + 65535 / 65536) {
                throw new \InvalidArgumentException('Axis values require finite signed 16.16 coordinates.');
            }
        }
        if ($minimum > $default || $default > $maximum || $flags < 0 || $flags > 65535 || $nameId < 256 || $nameId > 32767) {
            throw new \InvalidArgumentException('Invalid fvar axis range, flags or name identifier.');
        }
    }

    public function hidden(): bool
    {
        return ($this->flags & 1) !== 0;
    }

    /** Return a signed 16.16 integer, without the final 2.14 rounding. */
    public function normalize(float $value): int
    {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('User coordinates must be finite.');
        }
        $value = (int) floor(max($this->minimum, min($this->maximum, $value)) * 65536 + 0.5);
        $minimum = (int) floor($this->minimum * 65536 + 0.5);
        $default = (int) floor($this->default * 65536 + 0.5);
        $maximum = (int) floor($this->maximum * 65536 + 0.5);
        if ($value === $default) {
            return 0;
        }
        $normalized = ($value - $default) / ($value < $default ? $default - $minimum : $maximum - $default);

        return max(-65536, min(65536, (int) floor($normalized * 65536 + 0.5)));
    }
}
