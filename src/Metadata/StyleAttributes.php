<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Variation\UserCoordinates;

final readonly class StyleAttributes
{
    /** @param list<StyleAxis> $axes
     * @param list<StyleValue> $values
     */
    public function __construct(public int $minorVersion, public array $axes, public array $values, public ?int $elidedFallbackNameId = null)
    {
        if ($minorVersion < 0 || $minorVersion > 2 || !array_is_list($axes) || !array_is_list($values) || ($minorVersion === 0) !== ($elidedFallbackNameId === null) || ($elidedFallbackNameId !== null && ($elidedFallbackNameId < 0 || $elidedFallbackNameId > 32767))) {
            throw new \InvalidArgumentException('Invalid STAT version or collections.');
        }
        $tags = [];
        foreach ($axes as $axis) {
            if (!$axis instanceof StyleAxis || isset($tags[$axis->tag->value])) {
                throw new \InvalidArgumentException('STAT axes require unique tags.');
            }
            $tags[$axis->tag->value] = true;
        }
        foreach ($values as $value) {
            if (!$value instanceof StyleValue || ($minorVersion < 2 && $value->format === StyleValueFormat::Combination)) {
                throw new \InvalidArgumentException('STAT value formats must match their version.');
            }
            foreach ($value->coordinates as $coordinate) {
                if (!isset($axes[$coordinate->axisIndex])) {
                    throw new \InvalidArgumentException('A STAT value refers to an absent axis.');
                }
            }
        }
    }

    /** Returns every matching record in source order; UI name projection remains explicit.
     * @return list<StyleValue>
     */
    public function matching(UserCoordinates $coordinates): array
    {
        $indexed = [];
        foreach ($this->axes as $index => $axis) {
            if (isset($coordinates->values[$axis->tag->value])) {
                $indexed[$index] = $coordinates->values[$axis->tag->value];
            }
        }

        return array_values(array_filter($this->values, fn (StyleValue $value) => $value->matches($indexed)));
    }
}
