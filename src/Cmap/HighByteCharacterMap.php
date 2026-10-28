<?php

declare(strict_types=1);

namespace PDFToolkit\OpenType\Cmap;

use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\GlyphId;

/** Format 2 dispatches single-byte codes or a high-byte key to a low-byte map. */
final readonly class HighByteCharacterMap implements CharacterMapSubtable
{
    /** @param list<int> $keys
     * @param list<ArrayCharacterMap> $subHeaders
     */
    public function __construct(private array $keys, private array $subHeaders)
    {
        if (!array_is_list($keys) || count($keys) !== 256 || !array_is_list($subHeaders) || $subHeaders === []) {
            throw new \InvalidArgumentException('Format 2 requires 256 keys and at least one subheader.');
        }
        foreach ($subHeaders as $subHeader) {
            if (!$subHeader instanceof ArrayCharacterMap || $subHeader->format() !== 2) {
                throw new \InvalidArgumentException('Format 2 subheaders must contain format 2 arrays.');
            }
        }
        foreach ($keys as $key) {
            if (!isset($subHeaders[$key])) {
                throw new \InvalidArgumentException('A format 2 key must identify an existing subheader.');
            }
        }
    }

    public function format(): int
    {
        return 2;
    }

    public function glyphFor(CodePoint $codePoint): GlyphId
    {
        $value = $codePoint->value;
        if ($value > 0xFFFF) {
            return new GlyphId(0);
        }
        if ($value < 256) {
            return $this->keys[$value] === 0 ? $this->subHeaders[0]->glyphFor($codePoint) : new GlyphId(0);
        }
        $key = $this->keys[$value >> 8];

        return $key === 0 ? new GlyphId(0) : ($this->subHeaders[$key] ?? throw new \LogicException('Invalid validated subheader key.'))->glyphFor(new CodePoint($value & 255));
    }
}
