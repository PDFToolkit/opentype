<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\GlyphId;

final readonly class ContextMatcher
{
    /**
     * @param list<GlyphId> $glyphs
     * @return list<int>|null
     */
    public function match(ContextRule $rule, array $glyphs, int $position, LookupFlags $flags, GdefTable $definition, LayoutReadBudget $budget): ?array
    {
        $indices = [];
        $cursor = $position;
        foreach ($rule->input as $predicate) {
            $cursor = $this->next($glyphs, $cursor, 1, $flags, $definition, $budget);
            $budget->consume(1);
            if ($cursor === null || !$predicate->matches($glyphs[$cursor])) {
                return null;
            }
            $indices[] = $cursor;
            ++$cursor;
        }
        $cursor = $position - 1;
        foreach ($rule->backtrack as $predicate) {
            $cursor = $this->next($glyphs, $cursor, -1, $flags, $definition, $budget);
            $budget->consume(1);
            if ($cursor === null || !$predicate->matches($glyphs[$cursor])) {
                return null;
            }
            --$cursor;
        }
        $cursor = $indices[count($indices) - 1] + 1;
        foreach ($rule->lookahead as $predicate) {
            $cursor = $this->next($glyphs, $cursor, 1, $flags, $definition, $budget);
            $budget->consume(1);
            if ($cursor === null || !$predicate->matches($glyphs[$cursor])) {
                return null;
            }
            ++$cursor;
        }
        return $indices;
    }

    /** @param list<GlyphId> $glyphs */
    public function next(array $glyphs, int $position, int $direction, LookupFlags $flags, GdefTable $definition, LayoutReadBudget $budget): ?int
    {
        while (isset($glyphs[$position])) {
            $budget->consume(1);
            if (!$definition->ignores($glyphs[$position], $flags)) {
                return $position;
            }
            $position += $direction;
        }
        return null;
    }
}
