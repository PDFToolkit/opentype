# Glyph positioning and explicit variation instances

English | [Français](fr/positioning.md)

## Give each stage its own objects

Unicode mapping yields glyph identities. GSUB can replace those identities;
GPOS then adjusts their placement and advances. These stages are independent:
requesting `kern` does not implicitly request ligatures, mark positioning or a
script shaper. Use the same font's GDEF definitions for substitution and
positioning when a lookup uses glyph classifications or mark filtering.

```php
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

// $font and $sequence are explicit objects from the preceding stages.
$definition = (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount());
$gpos = (new GposReader())->read($font->table(new Tag('GPOS')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);
$initial = new PositionedGlyphSequence($sequence, array_map(
    fn (GlyphId $glyph): GlyphPosition => new GlyphPosition($metrics->advanceWidth($glyph)),
    $sequence->glyphs,
));
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('kern')]);
$positioned = (new PositioningEngine())->apply($gpos, $selection, $initial, $definition);
```

`GlyphPosition` contains `xAdvance`, `yAdvance`, `xOffset` and `yOffset` in design
units. The application supplies initial metrics and controls mark advance policy.
Placement affects the current glyph; advance affects the next origin. The engine
returns a new immutable `PositionedGlyphSequence` and preserves its input.

## Read and execute GPOS

Types 1–9 are native: single and pair adjustments, class-pair matrices, cursive
entry/exit attachment, mark-to-base, mark-to-ligature, mark-to-mark, contextual
and chained contextual positioning in all three formats, and extensions.
Required and requested features run in lookup-list order. The first matching
subtable wins; contextual actions invoke only their designated sequence positions.

Attachments retain their parent relation through later placement and advance
adjustments. Cursive `RIGHT_TO_LEFT` determines the cross-stream anchor of the
chain; it does not choose Unicode text direction. `PositioningContext(vertical:
true)` selects vertical advance adjustments. Bidirectional ordering, script
analysis, normalization, line breaking and vertical initial metrics belong to
the application or a separate shaper.

GSUB-produced `LigatureAssociation` identities keep marks on the correct
component, including when application clusters have equal values. Applications
constructing ligature glyphs directly can supply associations explicitly; a mark
without a matching ligature association uses the final component as defined by
OpenType. Cluster provenance and attachment identity are separate objects.

## Pixel devices, outline points and carets

`PositioningContext` makes units per em and optional pixels per em explicit.
Device formats 1/2/3 decode signed 2/4/8-bit pixel corrections. With no pixel
size, device corrections are zero. Results retain fractional design units;
HarfBuzz's integer output can differ by less than one design unit when a pixel
correction does not divide evenly into the chosen scale.

Anchor format 2 retains its point index and its design coordinates. An explicit
`AnchorPointResolver` can supply coordinates from a hinted outline. Without one,
its design coordinates remain usable. A GDEF point-index caret has no fallback
coordinate and therefore requires that capability. No font bytecode is executed
implicitly. GDEF attachment-point lists and ligature carets are accessible on
`GdefTable` as typed records.

## Native layout variations

`NormalizedCoordinates` expresses coordinates in axis order and retains the
required signed 2.14 precision after OpenType's 16.16 conversion. Normalization from
user-facing axes and `avar` remapping use the separate [axis objects](font-axes.md);
this API does not guess an instance. Supply coordinates in `FeatureSelection` to evaluate GSUB/GPOS
FeatureVariations. Condition sets run in precedence order; the first eligible
record replaces its feature tables. Unknown future conditions and unsupported
alternate-table versions fail that record and allow the next one.

GDEF 1.3 stores native `ItemVariationStore` data. `VariationInstance` evaluates
its regions and signed delta sets once, then answers `VariationIndex` references
without repeating interpolation for each glyph:

```php
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PDFToolkit\OpenType\Variation\VariationInstance;

$coordinates = new NormalizedCoordinates([0.5]); // Explicit one-axis instance.
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('kern')], coordinates: $coordinates);
$instance = new VariationInstance($definition->itemVariationStore, $coordinates);
$context = new PositioningContext($metrics->unitsPerEm, variations: $instance);
$positioned = (new PositioningEngine())->apply($gpos, $selection, $initial, $definition, $context);
```

Check that `itemVariationStore` exists before constructing an instance. A matched
VariationIndex without a supplied delta resolver fails explicitly. Layout variation remains separate from [metric instances](variable-metrics.md),
[TrueType variation](truetype-variations.md) and [CFF2 outlines](cff-outlines.md).
Those explicit objects do not write a static font file.

## Run the examples and independent checks

From a source checkout:

```sh
php examples/positioning.php
php examples/variable-layout.php
python tools/build_advanced_layout_fixture.py --check
python tools/build_variation_fixture.py --check
python tools/validate_advanced_layout.py --php php
python tools/validate_variations.py --php php
```

The original test font's `a,b` pair changes from advances `[600,600]` to
`[530,600]`. The variable example evaluates `600 + 10 + 100 × 0.5 = 660`.
Synthetic feature tags such as `sng2` belong to that fixture; real applications
select tags present in their own fonts. Fixtures and test tools are excluded
from Composer archives; pass an application-supplied font path outside a checkout.

Independent oracles cover all contextual formats, direction-sensitive reverse
substitution, GDEF filtering, attachments, all device formats and seven normalized
variation instances. They validate this scope, not every OpenType table.

Sources: [GPOS](https://learn.microsoft.com/en-us/typography/opentype/spec/gpos),
[GDEF](https://learn.microsoft.com/en-us/typography/opentype/spec/gdef),
[layout tables](https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2)
and [variation stores](https://learn.microsoft.com/en-us/typography/opentype/spec/otvarcommonformats).
