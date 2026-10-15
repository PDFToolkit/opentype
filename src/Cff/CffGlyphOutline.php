<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Outline\OutlinePath;

/** Unhinted charstring-space geometry; width is absent in CFF2 and distinct from hmtx. */
final readonly class CffGlyphOutline
{
    public function __construct(public OutlinePath $path, public ?float $width, public int $stemCount, public ?CffComposite $composite = null)
    {
        if (($width !== null && !is_finite($width)) || $stemCount < 0 || $stemCount > 96) {
            throw new \InvalidArgumentException('Invalid CFF outline result.');
        }
    }
}
