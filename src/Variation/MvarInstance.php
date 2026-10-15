<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Value\Tag;

/** Font-wide deltas by explicit metric tag, never an implicit mutation of default metrics. */
final readonly class MvarInstance
{
    /** @var array<string, float> */
    private array $deltas;

    public function __construct(public MvarTable $table, public NormalizedCoordinates $coordinates)
    {
        $instance = $table->store === null ? null : new VariationInstance($table->store, $coordinates);
        $deltas = [];
        foreach ($table->records as $record) {
            $deltas[$record->tag->value] = $instance?->delta($record->index) ?? 0.0;
        }
        $this->deltas = $deltas;
    }

    public function delta(Tag $tag): float
    {
        return $this->deltas[$tag->value] ?? 0.0;
    }

    public function adjust(Tag $tag, float $defaultValue): float
    {
        if (!is_finite($defaultValue)) {
            throw new \InvalidArgumentException('Metric defaults must be finite.');
        }

        return $defaultValue + $this->delta($tag);
    }
}
