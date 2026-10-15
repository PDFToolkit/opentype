# TrueType outline and CVT variations

English | [Français](fr/truetype-variations.md)

`GvarReader` reads version 1.0 from an explicit SFNT and its previously decoded `TrueTypeOutlines`. It verifies the `fvar` axis count and `maxp`/outline glyph count, both offset-array widths, shared and embedded peak tuples, intermediate regions, shared/private point lists and packed byte/word/zero delta runs. Header and data boundaries, expanded records and execution work are bounded.

```php
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\MetricPhantomProvider;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Variation\GvarInstance;
use PDFToolkit\OpenType\Variation\GvarReader;

$gvar = (new GvarReader())->read($font, $outlines);
$instance = new GvarInstance($gvar, $normalizedCoordinates);
$provider = new MetricPhantomProvider($horizontalMetrics, $verticalMetrics);
$glyphOutline = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))
    ->glyph($outlines, $glyph, $provider, $instance);
$path = $glyphOutline->outline;
$advanceWidth = $glyphOutline->phantoms->advanceWidth();
```

The instance computes each tuple's scalar, infers omitted simple-glyph deltas from original contour coordinates, and sums the scaled deltas. Contours without references remain unchanged; a single reference translates the whole contour. Coincident-coordinate references with different deltas infer zero. Phantom points never receive inferred deltas. Composite pseudo-points adjust only XY placement, before any scaled-offset transformation; point-attached components ignore their pseudo-point deltas. Children are instantiated recursively and transformations never mutate source objects.

Four phantom points are separate from drawable points. `MetricPhantomProvider` derives their defaults from explicit horizontal and optional vertical metrics and declared glyph bounds. When vertical metrics are absent, its vertical phantom defaults are zero. `glyph()` applies horizontal phantom X deltas and vertical phantom Y deltas, and the last component with `USE_MY_METRICS` selects its child's unhinted phantom points. Phantom-point component attachment is available when a provider is supplied. `outline()` needs no provider and returns geometry alone.

Metric selection is explicit: this path supplies the gvar phantom fallback; do not also apply HVAR/VVAR deltas to those advances. The library does not silently decide between those alternatives or round design-unit output to pixels. `GvarInstance::deltas()` also exposes tuple interpolation separately for callers with their own point and metric pipeline.

`ControlValueReader` reads every signed FWORD from `cvt `. `CvarReader` reads its version 1.0 tuple variation store, including required embedded peaks. `CvarTable::instance($defaults, $coordinates)` returns immutable design values. Missing CVT indices retain their default values; there is no contour interpolation for cvar. Fractional results are retained, leaving device scaling and pixel rounding to the instruction engine.

This is unhinted variation execution. `fpgm`, `prep` and glyph instruction bytes are not executed; no TrueType VM or rasterizer is claimed. The default glyph bounds remain source metadata; obtain bounds of an instantiated path from its resulting geometry. Sparse tuple interpolation is distinct from execution of the TrueType IUP instruction.

The original two-axis fixture includes sparse simple contours, all-off-curve contours, scaled and point-attached components, nested composites, phantom advances and cvar. The independent oracle compares 225 glyph instances, 100 CVT values and six `USE_MY_METRICS` cases with fontTools/HarfBuzz. Tests additionally cover both offset widths, malformed runs, missing tuples, bounds, budgets, contour edge cases and phantom attachment.

Sources: [gvar](https://learn.microsoft.com/en-us/typography/opentype/spec/gvar), [cvar](https://learn.microsoft.com/en-us/typography/opentype/spec/cvar), [variation common formats](https://learn.microsoft.com/en-us/typography/opentype/spec/otvarcommonformats), [variation overview](https://learn.microsoft.com/en-us/typography/opentype/spec/otvaroverview).
