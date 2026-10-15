<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

final readonly class GaspTable
{
    /** @param non-empty-list<GaspRange> $ranges */
    public function __construct(public int $version, public array $ranges)
    {
        if (!in_array($version, [0, 1], true) || $ranges === [] || !array_is_list($ranges)) {
            throw new \InvalidArgumentException('gasp needs versioned ordered ranges.');
        }
        $previous = -1;
        foreach ($ranges as $range) {
            if (!$range instanceof GaspRange || $range->maximumPpem <= $previous || ($version === 0 && $range->behavior > 3)) {
                throw new \InvalidArgumentException('gasp ranges must increase and use versioned behavior bits.');
            }
            $previous = $range->maximumPpem;
        }
        if ($previous !== 65535) {
            throw new \InvalidArgumentException('The final gasp range must cover all ppem sizes.');
        }
    }

    public function atPpem(int $ppem): GaspRange
    {
        if ($ppem < 0 || $ppem > 65535) {
            throw new \InvalidArgumentException('A gasp ppem must fit uint16.');
        }
        foreach ($this->ranges as $range) {
            if ($ppem <= $range->maximumPpem) {
                return $range;
            }
        }
        throw new \LogicException('The final gasp range covers uint16 ppem.');
    }
}
