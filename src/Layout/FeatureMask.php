<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Substitution\FeatureRange;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** One feature's ordered range overrides, independent from glyph and Unicode identities. */
final readonly class FeatureMask
{
    /** @param list<FeatureRange> $ranges */
    public function __construct(private bool $defaultEnabled, private array $ranges = [])
    {
        if (!array_is_list($ranges)) {
            throw new \InvalidArgumentException('Feature mask ranges must form an ordered list.');
        }
        foreach ($ranges as $range) {
            if (!$range instanceof FeatureRange) {
                throw new \InvalidArgumentException('Feature mask ranges must be explicit objects.');
            }
        }
    }

    public function allows(int $cluster, LayoutReadBudget $budget): bool
    {
        $enabled = $this->defaultEnabled;
        foreach ($this->ranges as $range) {
            $budget->consume(1);
            if ($cluster >= $range->start && $cluster < $range->end) {
                $enabled = $range->enabled;
            }
        }
        return $enabled;
    }
}
