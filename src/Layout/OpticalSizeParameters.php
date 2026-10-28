<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

/** GPOS size values use decipoints; the lower range boundary is exclusive. */
final readonly class OpticalSizeParameters implements FeatureParameters
{
    public function __construct(
        public int $designSize,
        public int $subfamilyId,
        public int $subfamilyNameId,
        public int $rangeStart,
        public int $rangeEnd,
    ) {
        foreach ([$designSize, $subfamilyId, $subfamilyNameId, $rangeStart, $rangeEnd] as $value) {
            if ($value < 0 || $value > 65535) {
                throw new \InvalidArgumentException('Optical size parameters are unsigned 16-bit values.');
            }
        }
        if ($designSize === 0 || ($subfamilyId !== 0 && ($subfamilyNameId < 256 || $subfamilyNameId > 32767 || $rangeStart >= $rangeEnd))) {
            throw new \InvalidArgumentException('Invalid optical design size, subfamily name or range.');
        }
    }

    public function contains(float $pointSize): bool
    {
        if (!is_finite($pointSize) || $pointSize <= 0) {
            throw new \InvalidArgumentException('An optical point size must be finite and positive.');
        }

        return $this->subfamilyId !== 0 && $pointSize * 10 > $this->rangeStart && $pointSize * 10 <= $this->rangeEnd;
    }
}
