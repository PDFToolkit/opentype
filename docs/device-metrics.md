# Precomputed device metrics

English | [Français](fr/device-metrics.md)

The `DeviceMetrics` objects read the font producer's `hdmx`, `LTSH` and `VDMX`
declarations. They return pixel metrics or linear-scaling thresholds without
executing TrueType instructions or invoking a rasterizer.

```php
use PDFToolkit\OpenType\DeviceMetrics\HorizontalDeviceMetricsReader;
use PDFToolkit\OpenType\DeviceMetrics\LinearThresholdReader;
use PDFToolkit\OpenType\DeviceMetrics\VerticalDeviceMetricsReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

$widths = (new HorizontalDeviceMetricsReader())->read(
    $font->table(new Tag('hdmx')), $font->glyphCount(),
);
$pixelWidth = $widths->atPpem(12)?->width(new GlyphId(2));
$thresholds = (new LinearThresholdReader())->read(
    $font->table(new Tag('LTSH')), $font->glyphCount(),
);
$declaredLinear = $thresholds->declaresLinear(new GlyphId(2), 50);
$heights = (new VerticalDeviceMetricsReader())->read($font->table(new Tag('VDMX')));
$height = $heights->select(96, 72, 1)?->atPpem(12);
```

`hdmx` version 0 retains each ordered ppem record and every unsigned pixel width.
The caller supplies the desired ppem; a missing record returns `null`, without
interpolation or a guessed design-unit width. Record alignment, zero padding,
maximum width, glyph count and aggregate allocation limits are checked.

`LTSH` version 0 retains one threshold per glyph. `declaresLinear()` compares an
explicit vertical ppem with that declaration. It does not verify the producer's
claim by executing the font. Use of these device tables and the related `head`
flag remains an explicit application choice.

`VDMX` versions 0/1 retain ordered aspect-ratio records and their shared groups.
Selection compares integer resolutions by cross multiplication and chooses the
first matching character set and ratio, including a final default ratio when
present. Character set 1 means the Windows ANSI subset in version 0; version 1
uses the revised whole-font meaning. The caller supplies that selector. A missing
ppem or ratio returns `null`; heights are never interpolated. Pixel bounds,
record ordering, relative offsets, distinct group counts and aggregate budgets
are validated before following or allocating records.

The reproducible fixture `ToolkitDeviceMetrics.ttf` contains original declared
values, not claimed rasterizer measurements. The independent fontTools oracle
checks every field and selections in both VDMX versions. Native tests additionally
exercise truncation, padding, counts, duplicate sizes, inverted bounds and budgets.

Sources: [hdmx](https://learn.microsoft.com/en-us/typography/opentype/spec/hdmx),
[LTSH](https://learn.microsoft.com/en-us/typography/opentype/spec/ltsh),
[VDMX](https://learn.microsoft.com/en-us/typography/opentype/spec/vdmx).
