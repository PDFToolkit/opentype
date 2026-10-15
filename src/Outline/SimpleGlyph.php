<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class SimpleGlyph implements GlyphDescription
{
    public function __construct(public GlyphBounds $bounds, public QuadraticOutline $outline, private string $program = '', public bool $overlap = false)
    {
    }

    public function instructions(): string
    {
        return $this->program;
    }
}
