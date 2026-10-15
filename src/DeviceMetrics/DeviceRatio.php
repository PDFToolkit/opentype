<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

final readonly class DeviceRatio
{
    public function __construct(public int $characterSet, public int $xRatio, public int $yStartRatio, public int $yEndRatio, public VerticalDeviceGroup $group)
    {
        if ($characterSet < 0 || $characterSet > 1 || $xRatio < 0 || $xRatio > 255 || $yStartRatio < 0 || $yEndRatio > 255 || $yEndRatio < $yStartRatio || ($xRatio === 0 && ($yStartRatio !== 0 || $yEndRatio !== 0)) || ($xRatio > 0 && $yStartRatio === 0)) {
            throw new \InvalidArgumentException('Invalid VDMX ratio.');
        }
    }
    public function matches(int $xResolution, int $yResolution): bool
    {
        if ($xResolution < 1 || $xResolution > 0xFFFFFFFF || $yResolution < 1 || $yResolution > 0xFFFFFFFF) {
            throw new \InvalidArgumentException('Device resolutions must be positive uint32 values.');
        }
        return $this->xRatio === 0 || ($yResolution * $this->xRatio >= $xResolution * $this->yStartRatio && $yResolution * $this->xRatio <= $xResolution * $this->yEndRatio);
    }
}
