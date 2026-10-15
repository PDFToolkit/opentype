# TrueType outlines

English | [Français](fr/truetype-outlines.md)

`TrueTypeOutlineReader` decodes both `loca` formats and simple/composite `glyf`
records. It returns immutable point, contour, component, transform, bound and
instruction-byte objects. The reader does not execute the retained instructions.

```php
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\QuadraticPathBuilder;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Value\GlyphId;

$outlines = (new TrueTypeOutlineReader())->read($font);
$outline = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))
    ->outline($outlines, new GlyphId(2));
$path = (new QuadraticPathBuilder())->build($outline);
```

The engine assembles unhinted default-instance components, applying their affine
transforms, explicit scaled/unscaled offsets and contour-point attachment.
Absent offset-scaling flags use the caller's policy: unscaled, scaled or reject
ambiguous transforms. The recommended platform behavior is unscaled. Nested
components are resolved in font order; repeated children are legitimate, cycles
are refused. Separate depth, operation and expanded-point budgets prevent a
small acyclic graph from allocating an unbounded outline.

`QuadraticPathBuilder` turns on/off-curve points into explicit move, line,
quadratic and close commands, including implied midpoints and contours with no
explicit on-curve point. `GlyphBounds` is the declared control-point rectangle,
not a claim that it is the tight Bézier bound or a varied/hinted rectangle.

Instructions, grid fitting, phantom-point attachment and gvar deltas are distinct
mechanisms. The engine does not execute them. ROUND_XY_TO_GRID and USE_MY_METRICS
are retained flags; this unhinted geometry operation neither rounds to pixels nor
selects final glyph metrics. A phantom-point attachment cannot be resolved from
contour points alone and currently fails explicitly. Empty glyph data and
zero-contour glyphs with instructions remain distinguishable.

The original fixture exercises curves, all-off-curve contours, offsets, uniform
and matrix transforms, point attachment and nested components. The independent
oracle compares 78 glyphs / 250 points from all current TrueType fixtures with
fontTools, and their drawn quadratic geometry with HarfBuzz. HarfBuzz's horizontal
origin adjustment (lsb minus xMin) is accounted for explicitly. Native tests cover
short/long loca, cycles, malformed flags/ranges, instructions and output budgets.

Sources: [glyf](https://learn.microsoft.com/en-us/typography/opentype/spec/glyf),
[loca](https://learn.microsoft.com/en-us/typography/opentype/spec/loca), and
[TrueType instructions and phantom points](https://learn.microsoft.com/en-us/typography/opentype/spec/tt_instructing_glyphs).
