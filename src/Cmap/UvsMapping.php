<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class UvsMapping
{
    public function __construct(public CodePoint $unicodeValue, public GlyphId $glyphId)
    {
    }
}
