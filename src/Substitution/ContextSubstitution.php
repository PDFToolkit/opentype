<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

use PDFToolkit\OpenType\Layout\ContextRule;
use PDFToolkit\OpenType\Layout\Coverage;

final readonly class ContextSubstitution implements SubstitutionSubtable
{
    /** @param list<ContextRule> $rules */
    public function __construct(public int $type, public int $format, public Coverage $coverage, public array $rules)
    {
        if (!in_array($type, [5, 6], true) || $format < 1 || $format > 3 || !array_is_list($rules)) {
            throw new \InvalidArgumentException('Invalid contextual substitution type or format.');
        }
        foreach ($rules as $rule) {
            if (!$rule instanceof ContextRule) {
                throw new \InvalidArgumentException('Context subtables require ContextRule objects.');
            }
        }
    }

    public function lookupType(): int
    {
        return $this->type;
    }

    public function isSupported(): bool
    {
        return true;
    }
}
