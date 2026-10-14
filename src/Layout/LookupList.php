<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

final readonly class LookupList
{
    /** @var list<Lookup> */
    public array $lookups;

    /** @param list<Lookup> $lookups */
    public function __construct(array $lookups)
    {
        foreach ($lookups as $lookup) {
            if (!$lookup instanceof Lookup) {
                throw new \InvalidArgumentException('Every collection element must be a Lookup object.');
            }
        }

        $this->lookups = array_values($lookups);
    }
}
