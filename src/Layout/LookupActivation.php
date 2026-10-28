<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** A lookup executes wherever any selecting feature is enabled. */
final readonly class LookupActivation
{
    /** @param non-empty-list<FeatureMask> $masks */
    public function __construct(public int $lookupIndex, private array $masks)
    {
        if ($lookupIndex < 0 || !array_is_list($masks) || $masks === []) {
            throw new \InvalidArgumentException('A lookup activation requires a non-negative index and feature masks.');
        }
        foreach ($masks as $mask) {
            if (!$mask instanceof FeatureMask) {
                throw new \InvalidArgumentException('Lookup activation masks must be explicit objects.');
            }
        }
    }

    public function allows(int $cluster, LayoutReadBudget $budget): bool
    {
        foreach ($this->masks as $mask) {
            if ($mask->allows($cluster, $budget)) {
                return true;
            }
        }
        return false;
    }
}
