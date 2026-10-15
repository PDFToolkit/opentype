<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class PathCommand
{
    /** @param list<OutlinePoint> $points */
    public function __construct(public PathOperator $operator, public array $points = [])
    {
        $count = match ($operator) {
            PathOperator::Close => 0,
            PathOperator::Quadratic => 2,
            PathOperator::Cubic => 3,
            default => 1,
        };
        if (!array_is_list($points) || count($points) !== $count) {
            throw new \InvalidArgumentException('Outline path operands do not match their operator.');
        }
        foreach ($points as $point) {
            if (!$point instanceof OutlinePoint) {
                throw new \InvalidArgumentException('Path commands require immutable points.');
            }
        }
    }
}
