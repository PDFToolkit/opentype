<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Layout;

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Exception\MalformedFontException;
use PDFToolkit\OpenType\Exception\UnsupportedFontException;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;

final readonly class FeatureParametersReader
{
    public function read(BinaryView $table, Tag $tag, LayoutReadBudget $budget): FeatureParameters
    {
        try {
            if ($tag->value === 'size') {
                return new OpticalSizeParameters($table->uint16(0), $table->uint16(2), $table->uint16(4), $table->uint16(6), $table->uint16(8));
            }
            if (preg_match('/^ss(?:0[1-9]|1[0-9]|20)$/D', $tag->value) === 1) {
                if ($table->uint16(0) !== 0) {
                    throw new UnsupportedFontException('Unsupported stylistic-set feature parameters version.');
                }

                return new StylisticSetParameters($table->uint16(2));
            }
            if (preg_match('/^cv(?:0[1-9]|[1-9][0-9])$/D', $tag->value) !== 1) {
                throw new MalformedFontException('This feature tag does not define feature parameters.');
            }
            if ($table->uint16(0) !== 0) {
                throw new UnsupportedFontException('Unsupported character-variant feature parameters version.');
            }
            $count = $table->uint16(12);
            $budget->consume($count);
            $table->bytes(14, 3 * $count);
            $characters = [];
            for ($index = 0; $index < $count; ++$index) {
                $characters[] = new CodePoint($table->uint24(14 + 3 * $index));
            }

            return new CharacterVariantParameters($table->uint16(2), $table->uint16(4), $table->uint16(6), $table->uint16(8), $table->uint16(10), $characters);
        } catch (\InvalidArgumentException $error) {
            throw new MalformedFontException('Invalid semantic feature parameters.', 0, $error);
        }
    }
}
