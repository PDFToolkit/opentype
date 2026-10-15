<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\DeviceMetrics;

final readonly class VerticalDeviceMetrics
{
    /** @param list<DeviceRatio> $ratios */
    public function __construct(public int $version, public array $ratios)
    {
        if (!in_array($version, [0, 1], true) || !array_is_list($ratios) || $ratios === []) {
            throw new \InvalidArgumentException('VDMX requires a supported version and ratio groups.');
        }
        foreach ($ratios as $index => $ratio) {
            if (!$ratio instanceof DeviceRatio || ($ratio->xRatio === 0 && $index !== count($ratios) - 1)) {
                throw new \InvalidArgumentException('A VDMX default ratio must be last.');
            }
        }
    }
    /** Character-set selection is explicit: version 0 value 1 means the Windows ANSI subset. */
    public function select(int $xResolution, int $yResolution, int $characterSet): ?VerticalDeviceGroup
    {
        if ($characterSet < 0 || $characterSet > 1) {
            throw new \InvalidArgumentException('Unsupported VDMX character-set selector.');
        }
        foreach ($this->ratios as $ratio) {
            if ($ratio->matches($xResolution, $yResolution) && $ratio->characterSet === $characterSet) {
                return $ratio->group;
            }
        }
        return null;
    }
}
