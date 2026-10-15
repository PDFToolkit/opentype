<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

final readonly class MarkRecord
{
    public function __construct(public int $markClass, public Anchor $markAnchor)
    {
        if ($markClass < 0 || $markClass > 65535) {
            throw new \InvalidArgumentException('Invalid mark attachment class.');
        }
    }
}
