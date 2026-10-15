<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cff;

use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Variation\ItemVariationStore;

final readonly class CffFont
{
    /**
     * @param list<CffFontDictionary> $fontDictionaries
     * @param list<int> $fontSelection
     */
    public function __construct(public bool $cff2, public int $unitsPerEm, public CffDictionary $topDictionary, public CffIndex $charStrings, public CffIndex $globalSubroutines, public array $fontDictionaries, public array $fontSelection, public ?ItemVariationStore $variationStore = null, public ?CffCharacterSet $characterSet = null, public ?CffEncoding $encoding = null)
    {
        if ($unitsPerEm < 16 || $unitsPerEm > 16384 || $charStrings->objects === [] || !array_is_list($fontDictionaries) || $fontDictionaries === [] || !array_is_list($fontSelection) || count($fontSelection) !== count($charStrings->objects)) {
            throw new \InvalidArgumentException('Invalid CFF font dimensions.');
        }
        foreach ($fontDictionaries as $dictionary) {
            if (!$dictionary instanceof CffFontDictionary) {
                throw new \InvalidArgumentException('CFF fonts require immutable dictionaries.');
            }
        }
        foreach ($fontSelection as $index) {
            if (!isset($fontDictionaries[$index])) {
                throw new \InvalidArgumentException('A CFF glyph selects an absent FontDICT.');
            }
        }
    }

    public function matrixFor(GlyphId $glyph): CffFontMatrix
    {
        $top = $this->matrix($this->topDictionary, new CffFontMatrix());
        $dictionary = $this->dictionaryFor($glyph)->dictionary;
        if (!$this->cff2 && $this->characterSet?->cidKeyed) {
            return $top->compose($this->matrix($dictionary, new CffFontMatrix(1, 0, 0, 1)));
        }

        return $top;
    }

    private function matrix(CffDictionary $dictionary, CffFontMatrix $default): CffFontMatrix
    {
        $operands = $dictionary->operands(1207);
        if ($operands === null) {
            return $default;
        }
        if (count($operands) !== 6) {
            throw new \PDFToolkit\OpenType\Exception\MalformedFontException('FontMatrix requires six operands.');
        }

        return new CffFontMatrix(...$operands);
    }

    public function dictionaryFor(GlyphId $glyph): CffFontDictionary
    {
        $index = $this->fontSelection[$glyph->value] ?? throw new \OutOfBoundsException('The CFF glyph exceeds its font.');

        return $this->fontDictionaries[$index];
    }
}
