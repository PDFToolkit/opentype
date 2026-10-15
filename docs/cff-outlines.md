# CFF and CFF2 outlines

English | [Français](fr/cff-outlines.md)

`CffReader` consumes an application-supplied `SfntFont` with the `OTTO` signature. It reads CFF1 or CFF2 INDEX and DICT structures, CharStrings, global/local subroutines, FontDICT selection (formats 0/3, and CFF2 format 4), and the CFF2 variation store. Counts, offsets, aggregate dictionary data and program sizes are bounded. CFF2 store axes must match `fvar` and its FontMatrix must match `unitsPerEm`.

```php
use PDFToolkit\OpenType\Cff\CffOutlineEngine;
use PDFToolkit\OpenType\Cff\CffReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

$cff = (new CffReader())->read($font);
$glyph = new GlyphId(1);
$outline = (new CffOutlineEngine())->outline(
    $cff,
    $glyph,
    new NormalizedCoordinates([0.5, -0.5]), // original fvar order
);
$designPath = $cff->matrixFor($glyph)->transform($outline->path, $cff->unitsPerEm);
```

Coordinates are needed when a CFF2 program uses `vsindex` or `blend`; the engine never chooses a variation instance implicitly. The result contains closed, absolute cubic paths in **charstring coordinates**. FontMatrix conversion is explicit. CFF1 CID FontDICT matrices concatenate with the TopDICT matrix. CFF1 charstring widths are separate from `hmtx`; CFF2 returns no charstring width.

The engine executes every Type 2 line/curve/flex operator, biased local/global subroutine calls, CFF1 arithmetic and transient storage, and CFF2 `vsindex`/`blend`. Each glyph execution has separate state. An application-supplied `CffRandomSource` is required for CFF1 `random`; there is no hidden random generator. Instruction, point, operand-stack, subroutine-depth and composite-depth budgets are explicit, and recursive cycles fail. Deprecated name-keyed `endchar` composites resolve their base/accent through **StandardEncoding**, independently of the font's own encoding.

CFF1 charsets include formats 0/1/2 and all three predefined charsets. The immutable charset exposes SID/CID identities and original custom-string bytes. Standard/Expert encodings and custom formats 0/1 with supplements provide explicit legacy code-to-glyph maps; Unicode selection continues to use `cmap`.

`CffPrivateDictionaryInstance` evaluates CFF2 PrivateDICT blends for explicit normalized coordinates and returns a new dictionary. Array operands retain the specified delta representation: interpreting BlueValues or StemSnap arrays is a separate hint-metadata operation. Uninterpreted DICT entries retain their original operator/operand sequence; this is not a claim that every PostScript metadata key has a high-level semantic object.

Stem and hint-mask instructions are consumed with validated lengths. Flex is rendered as two cubic curves. This is **unhinted geometry**, not CFF rasterization: stem fitting, blue-zone alignment, counter control and pixel-dependent flex selection are not applied. Synthetic fonts and historical multiple-master operators are outside the OpenType Type 2 implementation. TrueType instructions and `gvar` belong to separate mechanisms.

`tools/validate_cff.py` compares original, reproducible CFF1/CFF2 fixtures and the Unicode CFF fixture against fontTools and HarfBuzz, including every path operator, four flex operators, local/global calls, masks, custom encoding, deprecated composites and two-axis blends. Native tests cover malformed programs and INDEX structures, budgets, cycles, numbers, private blends and matrix composition.

Technical sources: [OpenType CFF](https://learn.microsoft.com/en-us/typography/opentype/spec/cff), [OpenType CFF2](https://learn.microsoft.com/en-us/typography/opentype/spec/cff2), [Adobe CFF specification 5176](https://partners.adobe.com/public/developer/en/font/5176.CFF.pdf), [Adobe Type 2 specification 5177](https://partners.adobe.com/public/developer/en/font/5177.Type2.pdf).
