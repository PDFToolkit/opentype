# Vertical and variable metrics

English | [Français](fr/variable-metrics.md)

`VerticalMetricsReader` reads all `vhea` 1.0/1.1 fields and `vmtx` advances/top
bearings, including repetition of the final full advance. `VerticalOriginsReader`
reads `VORG` default and sparse overrides for CFF/CFF2 fonts. It refuses use on a
TrueType font, whose origins require `glyf` and `vmtx`. These readers return default
metrics and never activate variation or hinting implicitly.

```php
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\GlyphMetricAxis;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationsReader;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationInstance;
use PDFToolkit\OpenType\Variation\MvarReader;
use PDFToolkit\OpenType\Variation\MvarInstance;

$table = (new GlyphMetricVariationsReader())->read(
    $font->table(new Tag('HVAR')), GlyphMetricAxis::Horizontal,
    $font->glyphCount(), count($fvar->axes),
);
$instance = new GlyphMetricVariationInstance($table, $coordinates);
$advance = $horizontalMetrics->advanceWidth(new GlyphId(2))
    + $instance->advanceDelta(new GlyphId(2));
$mvar = new MvarInstance(
    (new MvarReader())->read($font->table(new Tag('MVAR')), count($fvar->axes)),
    $coordinates,
);
$ascender = $mvar->adjust(new Tag('hasc'), $os2->sTypoAscender);
```

`GlyphMetricVariationsReader` supports HVAR/VVAR 1.0. It checks the explicit font
axis and glyph counts, packed delta-set maps and all referenced rows. Advances
use implicit glyph indices when their map is absent. A short map repeats its
last entry as specified. The no-variation sentinel yields zero. The shared
`DeltaSetIndexMapReader` implements both count formats; HVAR/VVAR require format 0.

The selected `GlyphMetricVariationInstance` returns floating design-unit deltas
from native region interpolation. Left/top and right/bottom bearings are named
`firstBearingDelta` and `secondBearingDelta`; missing optional maps return `null`,
not zero. `verticalOriginDelta` likewise preserves absence. TrueType phantom-point
variations may be needed when a map is absent. A consumer must not silently treat
absence as a constant metric or apply these deltas again after an outline engine
has already adjusted the same metrics.

`MvarReader` reads sorted tagged value records and their native store; `MvarInstance`
evaluates them and adjusts explicitly supplied defaults. Unlisted tags yield
zero. Private tags are retained and only used when explicitly requested. No
metrics object is mutated, no outline computation is inferred, and there is no
implicit policy for rounding, clamping advances or choosing line metrics.

A reproducible two-axis font exercises mapped advances, bearings, absent maps and
font-wide values. Across 30 instances, 2,520 glyph-metric and 90 font-wide values
are compared with fontTools. Horizontal/vertical advances also match HarfBuzz.
CFF VORG origins are independently checked through HarfBuzz; packed index formats,
widths, implicit mappings, sentinel values, invalid indices and budgets have
native tests.

Sources: [vhea](https://learn.microsoft.com/en-us/typography/opentype/spec/vhea),
[vmtx](https://learn.microsoft.com/en-us/typography/opentype/spec/vmtx),
[VORG](https://learn.microsoft.com/en-us/typography/opentype/spec/vorg),
[HVAR](https://learn.microsoft.com/en-us/typography/opentype/spec/hvar),
[VVAR](https://learn.microsoft.com/en-us/typography/opentype/spec/vvar),
[MVAR](https://learn.microsoft.com/en-us/typography/opentype/spec/mvar) and
[variation formats](https://learn.microsoft.com/en-us/typography/opentype/spec/otvarcommonformats).
