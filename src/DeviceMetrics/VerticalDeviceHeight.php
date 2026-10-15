<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

final readonly class VerticalDeviceHeight
{
    public function __construct(public int $pixelsPerEm, public int $maximum, public int $minimum)
    {
        if ($pixelsPerEm < 1 || $pixelsPerEm > 65535 || $minimum < -32768 || $maximum > 32767 || $maximum < $minimum) {
            throw new \InvalidArgumentException('Invalid VDMX pixel height.');
        }
    }
}
