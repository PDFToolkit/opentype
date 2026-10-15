# User axes and normalization

English | [Français](fr/font-axes.md)

`FvarReader` reads the axes and named instances of `fvar` 1.0. `AvarReader`
reads the ordered piecewise maps of `avar` 1.0. These are independent immutable
objects; applications supply table bytes and explicit user coordinates.

```php
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\AvarReader;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;

$fvar = (new FvarReader())->read($font->table(new Tag('fvar')));
$avar = $font->hasTable(new Tag('avar'))
    ? (new AvarReader())->read($font->table(new Tag('avar')), count($fvar->axes))
    : null;
$coordinates = $fvar->normalize(new UserCoordinates(['wdth' => 81.25, 'wght' => 650.0]), $avar);
```

The output preserves **font axis order**, regardless of input key order. Missing
values use that axis's default; values outside its range are clamped. Unknown
axis tags are rejected. A zero-axis table produces an empty normalized instance.
Hidden axes retain their position and continue to participate in normalization.
The flag is a user-interface recommendation, not an instruction to drop an axis.

User values are converted to 16.16, normalized around the asymmetric default,
optionally remapped through `avar`, then rounded to 2.14. A degenerate axis never
divides by zero. The returned `NormalizedCoordinates` works directly with native
`VariationInstance` and `FeatureVariations`. This normalizes selection; it does
not instantiate glyph outlines or metric deltas.

Named records retain subfamily and optional PostScript name IDs and all user
coordinates. `instanceCoordinates($index)` selects one record explicitly. Name
lookup is a separate operation; a default instance exists even when there are
no named records. Duplicate instance records are preserved without automatic
UI enumeration or naming policy.

Readers bound offsets and aggregate counts before allocation, reject duplicate
axis tags, invalid ranges and out-of-range named instances. Maps must be ordered
and non-retrograde. As specified, a map missing one of the identity anchors
-1/0/+1 is disabled for that axis. Unknown versions, including `avar` 2, are
explicitly unsupported; reserved flags are preserved without assigning new
semantics. The initial header's record sizes are validated for version 1.0.

`tools/validate_axes.py` checks 89 user selections against fontTools and HarfBuzz,
including out-of-range and omitted values. HarfBuzz normalized results match
exactly. fontTools uses floating arithmetic: only non-grid comparisons allow
one 2.14 unit. Another 204 layout cases use the resulting coordinates and compare
exact glyphs and integer design-unit positions. Fractional positioning remains
covered by native tests rather than rounding it silently in this oracle.

Technical sources: [fvar](https://learn.microsoft.com/en-us/typography/opentype/spec/fvar),
[avar](https://learn.microsoft.com/en-us/typography/opentype/spec/avar), and
[normalization](https://learn.microsoft.com/en-us/typography/opentype/spec/otvaroverview#coordinate-scales-and-normalization).
