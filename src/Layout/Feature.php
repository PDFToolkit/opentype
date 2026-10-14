<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class Feature
{
    /** @var list<int> */
    public array $lookupListIndices;

    /** @param list<int> $lookupListIndices */
    public function __construct(array $lookupListIndices, public ?BinaryView $featureParams = null)
    {
        foreach ($lookupListIndices as $index) {
            if (!is_int($index) || $index < 0 || $index > 65535) {
                throw new MalformedFontException('Invalid lookup index.');
            }
        }
        $this->lookupListIndices = array_values($lookupListIndices);
    }
}
