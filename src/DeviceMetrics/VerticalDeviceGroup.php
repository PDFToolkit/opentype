<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

final readonly class VerticalDeviceGroup
{
    /** @param list<VerticalDeviceHeight> $heights */
    public function __construct(public int $startSize, public int $endSize, public array $heights)
    {
        if ($startSize < 0 || $endSize > 255 || $endSize < $startSize || !array_is_list($heights)) {
            throw new \InvalidArgumentException('Invalid VDMX group sizes.');
        }
        $previous = 0;
        foreach ($heights as $height) {
            if (!$height instanceof VerticalDeviceHeight || $height->pixelsPerEm <= $previous) {
                throw new \InvalidArgumentException('VDMX pixel heights must increase.');
            }
            $previous = $height->pixelsPerEm;
        }
    }
    public function atPpem(int $pixelsPerEm): ?VerticalDeviceHeight
    {
        if ($pixelsPerEm < 1) {
            throw new \InvalidArgumentException('A pixel size must be positive.');
        }
        foreach ($this->heights as $height) {
            if ($height->pixelsPerEm === $pixelsPerEm) {
                return $height;
            }
        }
        return null;
    }
}
