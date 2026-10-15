<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

/** Unhinted control points with explicit on/off-curve flags and contour endpoints. */
final readonly class QuadraticOutline
{
    /**
     * @param list<OutlinePoint> $points
     * @param list<int> $endPoints
     */
    public function __construct(public array $points, public array $endPoints)
    {
        if (!array_is_list($points) || !array_is_list($endPoints) || (($points === []) !== ($endPoints === []))) {
            throw new \InvalidArgumentException('Outline points and contours must be ordered lists.');
        }
        foreach ($points as $point) {
            if (!$point instanceof OutlinePoint) {
                throw new \InvalidArgumentException('Outlines contain immutable control points.');
            }
        }
        $previous = -1;
        foreach ($endPoints as $end) {
            if ($end <= $previous || $end >= count($points)) {
                throw new \InvalidArgumentException('Contour endpoints must be increasing point indices.');
            }
            $previous = $end;
        }
        if ($previous !== count($points) - 1) {
            throw new \InvalidArgumentException('Every point must belong to a contour.');
        }
    }
}
