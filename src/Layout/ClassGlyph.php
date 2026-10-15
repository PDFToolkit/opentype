<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class ClassGlyph implements GlyphPredicate
{
    public function __construct(public ClassDefinition $definition, public int $classValue)
    {
        if ($classValue < 0 || $classValue > 65535) {
            throw new \InvalidArgumentException('A glyph class is an unsigned 16-bit value.');
        }
    }

    public function matches(GlyphId $glyph): bool
    {
        return $this->definition->classOf($glyph) === $this->classValue;
    }
}
