<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Positioning;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Layout\ContextMatcher;
use PDFToolkit\OpenType\Layout\FeatureResolver;
use PDFToolkit\OpenType\Layout\GdefTable;
use PDFToolkit\OpenType\Layout\LookupFlags;
use PDFToolkit\OpenType\Sfnt\ReadLimits;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;

/** Native layout of an explicitly selected logical glyph sequence; Unicode shaping is separate. */
final readonly class PositioningEngine
{
    public function __construct(private ReadLimits $limits = new ReadLimits())
    {
    }

    public function apply(GposTable $table, FeatureSelection $selection, PositionedGlyphSequence $input, ?GdefTable $definition = null, PositioningContext $context = new PositioningContext()): PositionedGlyphSequence
    {
        if (count($input->sequence->glyphs) > $this->limits->maxOutputGlyphs || $table->glyphCount > $this->limits->maxGlyphs) {
            throw new MalformedFontException('The positioning input exceeds the configured glyph budget.');
        }
        foreach ($input->sequence->glyphs as $glyph) {
            if ($glyph->value >= $table->glyphCount) {
                throw new MalformedFontException('A positioning input glyph exceeds the font glyph set.');
            }
        }
        $definition ??= new GdefTable();
        $budget = new LayoutReadBudget($this->limits->maxSubstitutionOperations);
        $indices = (new FeatureResolver())->resolve($table->scriptList, $table->featureList, $selection, $budget, $table->featureVariations !== null);
        $visited = [];
        foreach ($indices as $index) {
            $this->preflight($table, $index, $definition, $budget, $visited);
        }
        $buffer = new PositionBuffer($input);
        foreach ($indices as $index) {
            for ($position = 0; $position < count($input->sequence->glyphs);) {
                $next = $this->applyAt($table, $index, $buffer, $position, $definition, $context, $budget, 0);
                $position = $next ?? $position + 1;
            }
        }
        return $buffer->result($budget, $this->limits->maxOutputGlyphs);
    }

    /** @param array<int, true> $visited */
    private function preflight(GposTable $table, int $index, GdefTable $definition, LayoutReadBudget $budget, array &$visited): void
    {
        if (isset($visited[$index])) {
            return;
        }
        $visited[$index] = true;
        $lookup = $table->lookups[$index] ?? throw new MalformedFontException('A positioning action refers to an absent lookup.');
        $budget->consume(1 + count($lookup->subtables));
        if ($lookup->lookupFlags->markFilteringSet !== null && !isset($definition->markGlyphSets[$lookup->lookupFlags->markFilteringSet])) {
            throw new MalformedFontException('A positioning lookup refers to an absent GDEF mark set.');
        }
        foreach ($lookup->subtables as $subtable) {
            $subtable = $subtable instanceof ExtensionPositioning ? $subtable->extension : $subtable;
            if (!$subtable instanceof SingleAdjustment && !$subtable instanceof PairAdjustment && !$subtable instanceof ClassPairAdjustment && !$subtable instanceof CursiveAttachment && !$subtable instanceof MarkAttachment && !$subtable instanceof ContextPositioning) {
                throw new UnsupportedFontException('This positioning subtable has no native execution implementation.');
            }
            if ($subtable instanceof ContextPositioning) {
                foreach ($subtable->rules as $rule) {
                    $budget->consume(1 + count($rule->lookupRecords));
                    foreach ($rule->lookupRecords as $record) {
                        $this->preflight($table, $record->lookupListIndex, $definition, $budget, $visited);
                    }
                }
            }
        }
    }

    private function applyAt(GposTable $table, int $index, PositionBuffer $buffer, int $position, GdefTable $definition, PositioningContext $context, LayoutReadBudget $budget, int $depth): ?int
    {
        $budget->consume(1);
        if ($depth >= $this->limits->maxLookupNesting) {
            throw new MalformedFontException('Nested positioning lookups exceed their depth budget.');
        }
        $glyphs = $buffer->input->sequence->glyphs;
        $lookup = $table->lookups[$index];
        $flags = $lookup->lookupFlags;
        if ($definition->ignores($glyphs[$position], $flags)) {
            return null;
        }
        foreach ($lookup->subtables as $subtable) {
            $budget->consume(1);
            $subtable = $subtable instanceof ExtensionPositioning ? $subtable->extension : $subtable;
            if ($subtable instanceof SingleAdjustment) {
                $covered = $subtable->coverage->indexOf($glyphs[$position]);
                if ($covered !== null) {
                    $buffer->set($position, $buffer->positions[$position]->adjusted($subtable->values[$subtable->format === 1 ? 0 : $covered], $context));
                    return $position + 1;
                }
            } elseif ($subtable instanceof PairAdjustment || $subtable instanceof ClassPairAdjustment) {
                $covered = $subtable->coverage->indexOf($glyphs[$position]);
                $second = (new ContextMatcher())->next($glyphs, $position + 1, 1, $flags, $definition, $budget);
                if ($covered === null || $second === null) {
                    continue;
                }
                $pair = null;
                if ($subtable instanceof ClassPairAdjustment) {
                    $pair = $subtable->classValues[$subtable->classDef1->classOf($glyphs[$position])][$subtable->classDef2->classOf($glyphs[$second])] ?? throw new MalformedFontException('A pair class exceeds its matrix.');
                } else {
                    foreach ($subtable->pairSets[$covered] as $record) {
                        $budget->consume(1);
                        if ($record->secondGlyph->value === $glyphs[$second]->value) {
                            $pair = [$record->value1, $record->value2];
                            break;
                        }
                    }
                }
                if ($pair !== null) {
                    $buffer->set($position, $buffer->positions[$position]->adjusted($pair[0], $context));
                    $buffer->set($second, $buffer->positions[$second]->adjusted($pair[1], $context));
                    return $subtable->hasSecondValue ? $second + 1 : $position + 1;
                }
            } elseif ($subtable instanceof CursiveAttachment) {
                $firstIndex = $subtable->coverage->indexOf($glyphs[$position]);
                $second = (new ContextMatcher())->next($glyphs, $position + 1, 1, $flags, $definition, $budget);
                $secondIndex = $second === null ? null : $subtable->coverage->indexOf($glyphs[$second]);
                if ($firstIndex === null || $secondIndex === null || $second === null) {
                    continue;
                }
                $exit = $subtable->entryExitRecords[$firstIndex]->exitAnchor;
                $entry = $subtable->entryExitRecords[$secondIndex]->entryAnchor;
                if ($exit === null || $entry === null) {
                    continue;
                }
                $firstAnchor = $exit->resolve($glyphs[$position], $context);
                $secondAnchor = $entry->resolve($glyphs[$second], $context);
                $first = $buffer->positions[$position];
                $next = $buffer->positions[$second];
                if ($context->vertical) {
                    $advance = $first->yOffset + $firstAnchor->y - $next->yOffset - $secondAnchor->y - $buffer->advanceBetween($position + 1, $second, true, $budget);
                    $buffer->set($position, new GlyphPosition($first->xAdvance, $advance, $first->xOffset, $first->yOffset));
                } else {
                    $advance = $first->xOffset + $firstAnchor->x - $next->xOffset - $secondAnchor->x - $buffer->advanceBetween($position + 1, $second, false, $budget);
                    $buffer->set($position, new GlyphPosition($advance, $first->yAdvance, $first->xOffset, $first->yOffset));
                }
                $rightToLeft = ($flags->value & 1) !== 0;
                $child = $rightToLeft ? $position : $second;
                $parent = $rightToLeft ? $second : $position;
                $sign = $rightToLeft ? -1 : 1;
                $buffer->attachments[$child] = new AttachmentLink($parent, $sign * ($firstAnchor->x - $secondAnchor->x), $sign * ($firstAnchor->y - $secondAnchor->y), $context->vertical, !$context->vertical);
                // Cursive input advances by one so the next pair can extend the chain.
                return $position + 1;
            } elseif ($subtable instanceof MarkAttachment) {
                $markIndex = $subtable->markCoverage->indexOf($glyphs[$position]);
                if ($markIndex === null) {
                    continue;
                }
                $target = $this->attachmentTarget($buffer, $position, $subtable->type, $flags, $definition, $budget);
                $targetIndex = $target === null ? null : $subtable->targetCoverage->indexOf($glyphs[$target]);
                if ($target === null || $targetIndex === null) {
                    continue;
                }
                if ($subtable->type === 6) {
                    $firstAssociation = $buffer->input->sequence->ligatureAssociations[$target];
                    $secondAssociation = $buffer->input->sequence->ligatureAssociations[$position];
                    if ($firstAssociation !== null && $secondAssociation !== null && ($firstAssociation->ligatureId !== $secondAssociation->ligatureId || $firstAssociation->componentIndex !== $secondAssociation->componentIndex)) {
                        continue;
                    }
                }
                $component = 0;
                $anchors = $subtable->targetAnchors[$targetIndex]->components;
                if ($subtable->type === 5) {
                    $component = count($anchors) - 1;
                    $baseAssociation = $buffer->input->sequence->ligatureAssociations[$target];
                    $markAssociation = $buffer->input->sequence->ligatureAssociations[$position];
                    if ($baseAssociation !== null && $markAssociation !== null && $baseAssociation->ligatureId === $markAssociation->ligatureId && $markAssociation->componentIndex !== null) {
                        $component = min($markAssociation->componentIndex, count($anchors) - 1);
                    }
                }
                $mark = $subtable->markRecords[$markIndex];
                $targetAnchor = $anchors[$component][$mark->markClass];
                if ($targetAnchor === null) {
                    continue;
                }
                $basePoint = $targetAnchor->resolve($glyphs[$target], $context);
                $markPoint = $mark->markAnchor->resolve($glyphs[$position], $context);
                $buffer->attachments[$position] = new AttachmentLink($target, $basePoint->x - $markPoint->x, $basePoint->y - $markPoint->y);
                return $position + 1;
            } elseif ($subtable instanceof ContextPositioning) {
                if ($subtable->coverage->indexOf($glyphs[$position]) === null) {
                    continue;
                }
                foreach ($subtable->rules as $rule) {
                    $matched = (new ContextMatcher())->match($rule, $glyphs, $position, $flags, $definition, $budget);
                    if ($matched === null) {
                        continue;
                    }
                    foreach ($rule->lookupRecords as $record) {
                        $target = $matched[$record->sequenceIndex] ?? throw new MalformedFontException('A positioning context index exceeds its matched input.');
                        $this->applyAt($table, $record->lookupListIndex, $buffer, $target, $definition, $context, $budget, $depth + 1);
                    }
                    return $matched[count($matched) - 1] + 1;
                }
            }
        }
        return null;
    }

    private function attachmentTarget(PositionBuffer $buffer, int $position, int $type, LookupFlags $flags, GdefTable $definition, LayoutReadBudget $budget): ?int
    {
        $glyphs = $buffer->input->sequence->glyphs;
        for ($index = $position - 1; $index >= 0; --$index) {
            $budget->consume(1);
            $class = $definition->glyphClassDef->classOf($glyphs[$index]);
            if ($type !== 6 && $class === 3) {
                continue;
            }
            if ($type === 6 && $definition->ignores($glyphs[$index], $flags)) {
                continue;
            }
            return $index;
        }
        return null;
    }
}
