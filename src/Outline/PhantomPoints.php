<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

/** Unhinted phantom points in pp1/pp2/pp3/pp4 order, separate from drawable contours. */
final readonly class PhantomPoints
{
    public function __construct(public OutlinePoint $left, public OutlinePoint $right, public OutlinePoint $top, public OutlinePoint $bottom)
    {
    }

    /** @return list<OutlinePoint> */
    public function points(): array
    {
        return [$this->left, $this->right, $this->top, $this->bottom];
    }

    public function advanceWidth(): float
    {
        return $this->right->x - $this->left->x;
    }

    public function advanceHeight(): float
    {
        return $this->top->y - $this->bottom->y;
    }
}
