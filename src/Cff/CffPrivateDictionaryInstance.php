<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Variation\ItemVariationStore;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

/** Evaluate CFF2 PrivateDICT variation values without applying rasterizer hints. */
final readonly class CffPrivateDictionaryInstance
{
    public function __construct(private ItemVariationStore $store, private NormalizedCoordinates $coordinates)
    {
        if (count($coordinates->values) !== $store->axisCount) {
            throw new \InvalidArgumentException('PrivateDICT coordinates must match the variation store.');
        }
    }

    public function evaluate(CffDictionary $dictionary): CffDictionary
    {
        $stack = $entries = [];
        $index = 0;
        $selected = $blended = $pendingBlend = false;
        $seen = [];
        foreach ($dictionary->entries as $entry) {
            $stack = array_merge($stack, $entry->operands);
            if (count($stack) > 513) {
                throw new MalformedFontException('PrivateDICT variation operand stack overflow.');
            }
            if ($entry->operator === 23) {
                $count = array_pop($stack);
                $data = $this->store->data[$index] ?? throw new MalformedFontException('PrivateDICT vsindex selects absent variation data.');
                $regions = count($data->regionIndices);
                if ($count === null || $count !== floor($count) || $count < 1 || $count > intdiv(count($stack), 1 + $regions)) {
                    throw new MalformedFontException('Invalid PrivateDICT blend operands.');
                }
                $count = (int) $count;
                $values = array_splice($stack, -$count * (1 + $regions));
                for ($at = 0; $at < $count; ++$at) {
                    $value = $values[$at];
                    foreach ($data->regionIndices as $regionAt => $region) {
                        $value += $values[$count + $at * $regions + $regionAt] * $this->store->regions[$region]->scalar($this->coordinates);
                    }
                    if (!is_finite($value)) {
                        throw new MalformedFontException('A PrivateDICT blend exceeds numeric bounds.');
                    }
                    $stack[] = $value;
                }
                $blended = $pendingBlend = true;
                continue;
            }
            if ($entry->operator === 22) {
                if ($selected || $blended || count($stack) !== 1 || $stack[0] !== floor($stack[0]) || $stack[0] < 0 || $stack[0] > 65535 || !isset($this->store->data[(int) $stack[0]])) {
                    throw new MalformedFontException('PrivateDICT vsindex must select valid data once, before blend.');
                }
                $index = (int) $stack[0];
                $selected = true;
            }
            if ($pendingBlend && !in_array($entry->operator, [6, 7, 8, 9, 10, 11, 1212, 1213], true)) {
                throw new MalformedFontException('This PrivateDICT key cannot receive blended values.');
            }
            if (isset($seen[$entry->operator])) {
                throw new MalformedFontException('Duplicate PrivateDICT key.');
            }
            $seen[$entry->operator] = true;
            $entries[] = new CffDictEntry($entry->operator, $stack);
            $stack = [];
            $pendingBlend = false;
        }
        if ($stack !== []) {
            throw new MalformedFontException('PrivateDICT blend has no following key.');
        }

        return new CffDictionary($entries);
    }
}
