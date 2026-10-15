<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

use PDFToolkit\OpenType\Value\Tag;

/** Application-supplied user values keyed by axis tag, never by sorted position. */
final readonly class UserCoordinates
{
    /** @param array<string, float> $values */
    public function __construct(public array $values = [])
    {
        foreach ($values as $tag => $value) {
            new Tag((string) $tag);
            if ((!is_float($value) && !is_int($value)) || !is_finite($value)) {
                throw new \InvalidArgumentException('User axis coordinates must be finite numbers.');
            }
        }
    }
}
