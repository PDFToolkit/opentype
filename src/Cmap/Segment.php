<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

/** One format 4 segment; its stored glyph array precedes the idDelta adjustment. */
final readonly class Segment
{
    /** @param list<int>|null $glyphIdArray */
    public function __construct(
        public int $startCode,
        public int $endCode,
        public int $idDelta,
        private ?array $glyphIdArray = null,
    ) {
        if ($startCode < 0 || $startCode > $endCode || $endCode > 65535 || $idDelta < -32768 || $idDelta > 32767) {
            throw new \InvalidArgumentException('A cmap segment requires ordered 16-bit character codes and a signed delta.');
        }
        if ($glyphIdArray !== null) {
            if (!array_is_list($glyphIdArray) || count($glyphIdArray) !== $endCode - $startCode + 1) {
                throw new \InvalidArgumentException('A cmap segment glyph array must cover its complete character range.');
            }
            foreach ($glyphIdArray as $glyph) {
                if (!is_int($glyph) || $glyph < 0 || $glyph > 65535) {
                    throw new \InvalidArgumentException('A cmap glyph array contains unsigned 16-bit glyph indices.');
                }
            }
        }
    }

    public function glyphIndexFor(int $code): int
    {
        if ($code < $this->startCode || $code > $this->endCode) {
            return 0;
        }
        if ($this->glyphIdArray === null) {
            return ($code + $this->idDelta) & 0xFFFF;
        }
        $glyph = $this->glyphIdArray[$code - $this->startCode];

        return $glyph === 0 ? 0 : ($glyph + $this->idDelta) & 0xFFFF;
    }
}
