<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

interface GlyphDescription
{
    /** Preserved TrueType instructions, never implicitly executed. */
    public function instructions(): string;
}
