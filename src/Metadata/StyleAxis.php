<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Value\Tag;

final readonly class StyleAxis
{
    public function __construct(public Tag $tag, public int $nameId, public int $ordering)
    {
        if ($nameId < 0 || $nameId > 32767 || $ordering < 0 || $ordering > 65535) {
            throw new \InvalidArgumentException('Invalid STAT axis name or ordering.');
        }
    }
}
