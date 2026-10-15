<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

/** Application capability required only by CFF1's random operator; range (0, 1]. */
interface CffRandomSource
{
    public function value(): float;
}
