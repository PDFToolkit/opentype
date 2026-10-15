<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Variation;

final readonly class NamedInstance
{
    /** @param list<float> $coordinates Values in original fvar axis order. */
    public function __construct(public int $subfamilyNameId, public int $flags, public array $coordinates, public ?int $postScriptNameId = null)
    {
        if ((!in_array($subfamilyNameId, [2, 17], true) && ($subfamilyNameId < 256 || $subfamilyNameId > 32767))
            || $flags < 0 || $flags > 65535
            || ($postScriptNameId !== null && !in_array($postScriptNameId, [6, 65535], true) && ($postScriptNameId < 256 || $postScriptNameId > 32767))
            || !array_is_list($coordinates)
        ) {
            throw new \InvalidArgumentException('Invalid named-instance records.');
        }
        foreach ($coordinates as $value) {
            if ((!is_float($value) && !is_int($value)) || !is_finite($value)) {
                throw new \InvalidArgumentException('Named-instance values must be finite.');
            }
        }
    }
}
