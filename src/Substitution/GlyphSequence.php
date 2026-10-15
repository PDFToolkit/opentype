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
    /** @var list<non-empty-list<int>> Source components retained by ligature substitutions. */
    public array $componentClusters;
    /** @var list<LigatureAssociation|null> Distinct from user-provided cluster numbers. */
    public array $ligatureAssociations;

    /**
     * @param list<GlyphId> $glyphs Logical input order
     * @param list<int>|null $clusters Original input positions; substitutions retain their provenance
     * @param list<non-empty-list<int>>|null $componentClusters
     * @param list<LigatureAssociation|null>|null $ligatureAssociations
     */
    public function __construct(array $glyphs, ?array $clusters = null, ?array $componentClusters = null, ?array $ligatureAssociations = null)
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
        $componentClusters ??= array_map(fn (int $cluster): array => [$cluster], $this->clusters);
        if (count($componentClusters) !== count($glyphs)) {
            throw new \InvalidArgumentException('Each glyph requires source component provenance.');
        }
        foreach ($componentClusters as $components) {
            if ($components === [] || !array_is_list($components)) {
                throw new \InvalidArgumentException('Component provenance must be a non-empty list.');
            }
            foreach ($components as $component) {
                if (!is_int($component) || $component < 0) {
                    throw new \InvalidArgumentException('Source components must be non-negative.');
                }
            }
        }
        $this->componentClusters = array_values($componentClusters);
        $ligatureAssociations ??= array_fill(0, count($glyphs), null);
        if (!array_is_list($ligatureAssociations) || count($ligatureAssociations) !== count($glyphs)) {
            throw new \InvalidArgumentException('Every glyph requires one nullable ligature association.');
        }
        foreach ($ligatureAssociations as $association) {
            if ($association !== null && !$association instanceof LigatureAssociation) {
                throw new \InvalidArgumentException('Ligature associations must be immutable objects.');
            }
        }
        $this->ligatureAssociations = $ligatureAssociations;
    }
}
