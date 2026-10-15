<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

/** @internal Deferred anchor relation, resolved after subsequent positioning adjustments. */
final readonly class AttachmentLink
{
    public function __construct(public int $parent, public float $xDifference, public float $yDifference, public bool $adjustX = true, public bool $adjustY = true)
    {
    }
}
