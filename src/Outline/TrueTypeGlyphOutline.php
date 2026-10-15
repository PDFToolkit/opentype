<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class TrueTypeGlyphOutline
{
    public function __construct(public QuadraticOutline $outline, public ?PhantomPoints $phantoms)
    {
    }
}
