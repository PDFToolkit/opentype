<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Exception\MalformedFontException;

final readonly class FeatureList
{
    /** @var list<FeatureRecord> */
    public array $featureRecords;

    /** @param list<FeatureRecord> $featureRecords */
    public function __construct(array $featureRecords)
    {
        foreach ($featureRecords as $record) {
            if (!$record instanceof FeatureRecord) {
                throw new \InvalidArgumentException('Every collection element must be a FeatureRecord object.');
            }
        }

        $previous = null;
        foreach ($featureRecords as $record) {
            if ($previous !== null && strcmp($previous, $record->featureTag->value) > 0) {
                throw new MalformedFontException('Feature tags must be ordered.');
            }
            $previous = $record->featureTag->value;
        }
        $this->featureRecords = array_values($featureRecords);
    }
}
