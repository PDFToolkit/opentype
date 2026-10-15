<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class ComponentTransform
{
    public function __construct(public float $xx = 1.0, public float $xy = 0.0, public float $yx = 0.0, public float $yy = 1.0)
    {
        foreach ([$xx, $xy, $yx, $yy] as $value) {
            if (!is_finite($value) || $value < -2 || $value >= 2) {
                throw new \InvalidArgumentException('Component transform entries must fit F2DOT14.');
            }
        }
    }

    public function apply(OutlinePoint $point): OutlinePoint
    {
        return new OutlinePoint($this->xx * $point->x + $this->yx * $point->y, $this->xy * $point->x + $this->yy * $point->y, $point->onCurve);
    }
}
