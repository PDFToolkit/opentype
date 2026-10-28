<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Value\Tag;

final readonly class FeatureSelection
{
    /** @var list<Tag> */
    public array $features;

    /** @param list<Tag> $features
     * @param list<FeatureRange> $ranges
     */
    public function __construct(
        public Tag $script,
        public ?Tag $language = null,
        array $features = [],
        public ?AlternateSelector $alternateSelector = null,
        public bool $fallbackToDefaultScript = true,
        public bool $fallbackToDefaultLanguage = true,
        public ?\PDFToolkit\OpenType\Variation\NormalizedCoordinates $coordinates = null,
        public array $ranges = [],
    ) {
        foreach ($features as $tag) {
            if (!$tag instanceof Tag) {
                throw new \InvalidArgumentException('Every collection element must be a Tag object.');
            }
        }

        if (!array_is_list($ranges)) {
            throw new \InvalidArgumentException('Feature ranges must form an ordered list.');
        }
        foreach ($ranges as $range) {
            if (!$range instanceof FeatureRange) {
                throw new \InvalidArgumentException('Feature ranges must be explicit objects.');
            }
        }
        $this->features = array_values($features);
    }
}
