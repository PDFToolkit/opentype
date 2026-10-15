<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Metadata;

use PDFToolkit\OpenType\Value\GlyphId;

final readonly class PostScriptTable
{
    /** @param list<string>|null $glyphNames */
    public function __construct(public int $version, public float $italicAngle, public int $underlinePosition, public int $underlineThickness, public int $isFixedPitch, public int $minMemType42, public int $maxMemType42, public int $minMemType1, public int $maxMemType1, public ?array $glyphNames)
    {
        if (!in_array($version, [0x10000, 0x20000, 0x25000, 0x30000], true) || !is_finite($italicAngle) || $italicAngle < -32768 || $italicAngle >= 32768 || $underlinePosition < -32768 || $underlinePosition > 32767 || $underlineThickness < -32768 || $underlineThickness > 32767 || ($version === 0x30000) !== ($glyphNames === null) || ($glyphNames !== null && !array_is_list($glyphNames))) {
            throw new \InvalidArgumentException('Invalid post fields or versioned glyph names.');
        }
        foreach ([$isFixedPitch, $minMemType42, $maxMemType42, $minMemType1, $maxMemType1] as $value) {
            if ($value < 0 || $value > 0xFFFFFFFF) {
                throw new \InvalidArgumentException('A post field exceeds uint32.');
            }
        }
    }

    public function glyphName(GlyphId $glyph): ?string
    {
        return $this->glyphNames === null ? null : ($this->glyphNames[$glyph->value] ?? throw new \OutOfBoundsException('A glyph exceeds its post names.'));
    }
}
