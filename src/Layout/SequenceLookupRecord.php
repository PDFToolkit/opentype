<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

final readonly class SequenceLookupRecord
{
    public function __construct(public int $sequenceIndex, public int $lookupListIndex)
    {
        if ($sequenceIndex < 0 || $sequenceIndex > 65535 || $lookupListIndex < 0 || $lookupListIndex > 65535) {
            throw new \InvalidArgumentException('Sequence lookup record indices must be unsigned 16-bit values.');
        }
    }
}
