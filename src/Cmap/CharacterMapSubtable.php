<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

interface CharacterMapSubtable
{
    public function glyphFor(CodePoint $codePoint): GlyphId;

    public function format(): int;
}
