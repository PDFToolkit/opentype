<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Substitution;

final readonly class LigatureSet
{
    /** @var list<Ligature> */
    public array $ligatures;

    /** @param list<Ligature> $ligatures Preference order from the font, not longest first. */
    public function __construct(array $ligatures)
    {
        foreach ($ligatures as $ligature) {
            if (!$ligature instanceof Ligature) {
                throw new \InvalidArgumentException('Every collection element must be a Ligature object.');
            }
        }

        $this->ligatures = array_values($ligatures);
    }
}
