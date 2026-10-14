<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class GlyphSequence
{
    /** @var list<GlyphId> */
    public array $glyphs;
    /** @var list<int> */
    public array $clusters;

    /**
     * @param list<GlyphId> $glyphs Logical input order
     * @param list<int>|null $clusters Original input positions; substitutions retain their provenance
     */
    public function __construct(array $glyphs, ?array $clusters = null)
    {
        foreach ($glyphs as $glyph) {
            if (!$glyph instanceof GlyphId) {
                throw new \InvalidArgumentException('Every collection element must be a GlyphId object.');
            }
        }

        $this->glyphs = array_values($glyphs);
        $clusters ??= $glyphs === [] ? [] : range(0, count($glyphs) - 1);
        if (count($clusters) !== count($glyphs)) {
            throw new \InvalidArgumentException('Each glyph requires one cluster.');
        }
        foreach ($clusters as $cluster) {
            if (!is_int($cluster) || $cluster < 0) {
                throw new \InvalidArgumentException('Clusters must be non-negative.');
            }
        }
        $this->clusters = array_values($clusters);
    }
}
