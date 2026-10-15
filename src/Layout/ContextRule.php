<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

/** Backtrack predicates are stored nearest preceding glyph first, as in the font. */
final readonly class ContextRule
{
    /** @param non-empty-list<GlyphPredicate> $input
     * @param list<SequenceLookupRecord> $lookupRecords
     * @param list<GlyphPredicate> $backtrack
     * @param list<GlyphPredicate> $lookahead
     */
    public function __construct(public array $input, public array $lookupRecords = [], public array $backtrack = [], public array $lookahead = [])
    {
        if ($input === [] || !array_is_list($input) || !array_is_list($backtrack) || !array_is_list($lookahead) || !array_is_list($lookupRecords)) {
            throw new \InvalidArgumentException('A context rule requires an ordered non-empty input.');
        }
        foreach ([$input, $backtrack, $lookahead] as $predicates) {
            foreach ($predicates as $predicate) {
                if (!$predicate instanceof GlyphPredicate) {
                    throw new \InvalidArgumentException('Context sequences contain GlyphPredicate objects.');
                }
            }
        }
        foreach ($lookupRecords as $record) {
            if (!$record instanceof SequenceLookupRecord) {
                throw new \InvalidArgumentException('Context actions contain SequenceLookupRecord objects.');
            }
        }
    }
}
