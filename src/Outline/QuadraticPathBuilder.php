<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

use PDFToolkit\OpenType\Exception\MalformedFontException;

/** Expands implied on-curve midpoints; no rasterization or hinting is performed. */
final readonly class QuadraticPathBuilder
{
    public function __construct(private OutlineLimits $limits = new OutlineLimits())
    {
    }

    public function build(QuadraticOutline $outline): OutlinePath
    {
        if (count($outline->points) > $this->limits->maxExpandedPoints || count($outline->points) + 2 * count($outline->endPoints) > $this->limits->maxOperations) {
            throw new MalformedFontException('The quadratic path exceeds its output budget.');
        }
        $commands = [];
        $first = 0;
        foreach ($outline->endPoints as $end) {
            $startPoint = $outline->points[$first];
            $endPoint = $outline->points[$end];
            $at = $first;
            $last = $end;
            if ($startPoint->onCurve) {
                ++$at;
            } elseif ($endPoint->onCurve) {
                $startPoint = $endPoint;
                --$last;
            } else {
                $startPoint = $this->midpoint($startPoint, $endPoint);
            }
            $commands[] = new PathCommand(PathOperator::Move, [$startPoint]);
            while ($at <= $last) {
                $point = $outline->points[$at];
                if ($point->onCurve) {
                    $commands[] = new PathCommand(PathOperator::Line, [$point]);
                    ++$at;
                } else {
                    $next = $at < $last ? $outline->points[$at + 1] : $startPoint;
                    if ($next->onCurve) {
                        $commands[] = new PathCommand(PathOperator::Quadratic, [$point, $next]);
                        $at += 2;
                    } else {
                        $commands[] = new PathCommand(PathOperator::Quadratic, [$point, $this->midpoint($point, $next)]);
                        ++$at;
                    }
                }
            }
            $commands[] = new PathCommand(PathOperator::Close);
            $first = $end + 1;
        }

        return new OutlinePath($commands);
    }

    private function midpoint(OutlinePoint $a, OutlinePoint $b): OutlinePoint
    {
        return new OutlinePoint(($a->x + $b->x) / 2, ($a->y + $b->y) / 2);
    }
}
