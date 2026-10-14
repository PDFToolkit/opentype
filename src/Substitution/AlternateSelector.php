<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\GlyphId;

interface AlternateSelector
{
    public function select(GlyphId $input, AlternateSet $alternates): GlyphId;
}
