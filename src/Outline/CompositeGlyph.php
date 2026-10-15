<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Outline;

final readonly class CompositeGlyph implements GlyphDescription
{
    /** @param non-empty-list<GlyphComponent> $components */
    public function __construct(public GlyphBounds $bounds, public array $components, private string $program = '')
    {
        if ($components === [] || !array_is_list($components)) {
            throw new \InvalidArgumentException('Composite glyphs require ordered components.');
        }
        foreach ($components as $component) {
            if (!$component instanceof GlyphComponent) {
                throw new \InvalidArgumentException('Composite glyphs contain immutable components.');
            }
        }
        if (!$components[0]->usesOffsets()) {
            throw new \InvalidArgumentException('The first component cannot attach to a preceding parent point.');
        }
    }

    public function instructions(): string
    {
        return $this->program;
    }
}
