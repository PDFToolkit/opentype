# Font headers, glyph names and style metadata

English | [Français](fr/font-headers-and-styles.md)

These explicit readers return independent immutable objects:

| Reader | Fields and behavior |
| --- | --- |
| `FontHeaderReader` | Every `head` 1.0 field, signed 64-bit timestamps in seconds since 1904 UTC, fixed revision and declared control-point bounds |
| `HorizontalHeaderReader` | Every `hhea` 1.0 metric, caret field and count; reserved fields checked |
| `MaximumProfileReader` | All `maxp` 1.0 declarations; 0.5 has only the glyph count and no invented TrueType limits |
| `PostScriptReader` | Header, version 1 standard names, version 2 indexed Pascal names, deprecated 2.5 offsets and version 3 absence of names |
| `GaspReader` | Versions 0/1, ordered ppem ranges and explicit rasterizer recommendation flags |
| `StyleAttributesReader` | STAT 1.0/1.1/1.2, axes with ordering/name references, all four axis-value formats and fallback name ID |

```php
use PDFToolkit\OpenType\Metadata\StyleAttributesReader;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;

$fvar = (new FvarReader())->read($font->table(new Tag('fvar')));
$styles = (new StyleAttributesReader())->read($font->table(new Tag('STAT')), $fvar);
$matches = $styles->matching(new UserCoordinates(['wght' => 700, 'wdth' => 81.25]));
```

STAT axis order is independent of fvar. Passing an fvar model checks that each dynamic axis has a corresponding STAT axis with the same name ID. Extended axis-record sizes are respected. `matching()` returns all matching point/range/linked/combination records in source order; range endpoints are inclusive, linked values remain style-link metadata, and absent user coordinates are not supplied implicitly. Name decoding, format-4 precedence when composing names, localization and R/B/I/BI or WWS family projection are application policies. Flags and linked values remain available explicitly.

All post 2 glyph-name bytes are bounded Pascal strings with 1–63 permitted ASCII characters. Version 1 requires the full standard 258-glyph order; version 3 exposes no names. A glyph name is not a Unicode mapping. The original signed head timestamps are preserved without converting through floating point or silently choosing a timezone. Header fields are declarations: neither `head` bounds nor `maxp` limits establish that every outline/instruction has been validated against them. Runtime budgets remain application-controlled.

`gasp->atPpem($ppem)` selects a recommendation without activating a rasterizer. MVAR adjustments to header, underline or gasp values remain explicit via the metric-variation API. Reading `gasp` does not apply grid fitting, antialiasing or ClearType.

The independent header oracle compares every field with fontTools across all fixtures and STAT 1.0/1.1/1.2 variants. Native cases additionally cover signed 64-bit timestamp boundaries, post 1/2/2.5/3, Pascal bounds, gasp version flags, STAT record-size extension and malformed relative offsets.

Sources: [head](https://learn.microsoft.com/en-us/typography/opentype/spec/head), [hhea](https://learn.microsoft.com/en-us/typography/opentype/spec/hhea), [maxp](https://learn.microsoft.com/en-us/typography/opentype/spec/maxp), [post](https://learn.microsoft.com/en-us/typography/opentype/spec/post), [gasp](https://learn.microsoft.com/en-us/typography/opentype/spec/gasp), [STAT](https://learn.microsoft.com/en-us/typography/opentype/spec/stat).
