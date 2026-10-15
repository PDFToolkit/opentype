<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class TupleVariation
{
    /** @param list<int>|null $points Null means every point in the target sequence.
     * @param list<TupleDelta> $deltas
     */
    public function __construct(public TupleRegion $region, public ?array $points, public array $deltas)
    {
        if (!array_is_list($deltas) || ($points !== null && (!array_is_list($points) || count($points) !== count($deltas)))) {
            throw new \InvalidArgumentException('Tuple point and delta counts must match.');
        }
        $previous = -1;
        foreach ($points ?? [] as $point) {
            if ($point <= $previous || $point > 65535) {
                throw new \InvalidArgumentException('Tuple point indices must increase.');
            }
            $previous = $point;
        }
        foreach ($deltas as $delta) {
            if (!$delta instanceof TupleDelta) {
                throw new \InvalidArgumentException('Tuple variations require immutable deltas.');
            }
        }
    }
}
