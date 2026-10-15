# From font bytes to explicit objects

English | [Français](fr/architecture.md)

## Application boundary

The application loads a font with its own storage policy. `SfntReader` consumes
those bytes and a `ReadLimits` object. There is no global font registry, shared
document state or PDF dependency. `BinaryView` represents bounded bytes; table
offsets are relative to their own view. The SFNT directory retains typed records
and table data, including tables whose semantics are not implemented.

## Identities and measurements

`Tag` represents four-byte, space-padded table, script, language and feature
identifiers. `CodePoint` is a Unicode scalar; `GlyphId` identifies a glyph within
a font. Unicode identity and glyph identity are separate contracts.

`UnicodeCmapReader` selects a supported base Unicode mapping. Formats 4/12 cover
the repertoire; format 14 describes variation sequences. Missing base characters
map to glyph zero; unsupported variation sequences remain distinguishable.

`HorizontalMetricsReader` exposes advances and left side bearings in font design
units, together with units per em. The caller chooses coordinate conversion. The
reader does not apply kerning or a variable-font instance.

## Layout objects

GSUB contains `ScriptList`, `FeatureList` and `LookupList`. A script selects a
language system, which references required and optional features. Features
reference lookups; coverage and substitution records describe transformations.

`GsubReader` constructs these objects. `FeatureSelection` expresses script,
language, features, fallback choices and an alternate policy.
`SubstitutionEngine` resolves that selection and returns a new `GlyphSequence`,
retaining source clusters. The objects can also be constructed directly. Their
constructors check local invariants; readers additionally check binary bounds and
cross-record references. Execution checks the selected lookup mechanisms.


## Definitions, positions and variation instances

`GdefTable` owns glyph/mark classes, mark sets, attachment-point indices, ligature
carets and optional native variation data. `GposReader` constructs immutable
values, anchors, pair matrices, attachment and context programs.
`PositionedGlyphSequence` pairs glyph identities with explicit initial
`GlyphPosition` objects; `PositioningEngine` returns adjusted objects. Mutable
buffers are scoped to one execution, including its deferred attachment graph.

`NormalizedCoordinates`, `FeatureVariations`, `ItemVariationStore` and
`VariationInstance` evaluate a caller-selected layout instance. Device scaling
and hinted outline-point access are explicit `PositioningContext` capabilities.
Ligature attachment identities are independent from source cluster numbers.
See the [positioning guide](positioning.md) for a complete example and limits.

## Metadata, axes and outline execution

`NameReader`, `Os2Reader`, header/profile readers and `StyleAttributesReader`
return declared metadata. Exact name decoding, language selection and style
matching are explicit. `FvarTable` normalizes tagged `UserCoordinates` in source
axis order; `AvarTable` remaps that result. The same normalized instance can feed
layout, metric and outline variation objects without mutating source tables.

`GlyphMetricVariationInstance` and `MvarInstance` return deltas or explicitly
adjusted values. `TrueTypeOutlineReader` decodes loca/glyf; `TrueTypeOutlineEngine`
assembles unhinted geometry and optional gvar/phantom metrics. `CvarTable` varies
unscaled CVT values. `CffReader` models programs and dictionaries;
`CffOutlineEngine` executes bounded cubic paths, subroutines and explicit blends.
A program's retained bytes, executed path and applied hint are distinct contracts.

`OutlineLimits` bounds bytes, records, instructions, expanded points, operations
and depth. Composite and subroutine cycles are refused. Phantom providers,
ambiguous composite-offset policy, CFF random sources and transforms are explicit.
Device metric readers select the font's precomputed declarations without invoking
a scaler. No engine supplies a hidden VM, rasterizer or complete Unicode shaper.

See [axes](font-axes.md), [metrics](variable-metrics.md),
[TrueType variation](truetype-variations.md), [CFF/CFF2](cff-outlines.md) and the
[complete coverage boundaries](coverage.md).

## Relationship to PDFToolkit

The PDF core owns PDF font dictionaries, CID mappings and text operators.
`pdf-fonts` converts supported external TrueType programs to those PDF objects.
`opentype` represents an independent font format and transformations. The core
needs no production dependency on this package.

Connecting selected glyphs to PDF content requires explicit glyph encoding,
positioning and Unicode extraction mappings. This release does not change the
existing converter or add implicit shaping to its output.

Technical sources: [SFNT](https://learn.microsoft.com/en-us/typography/opentype/spec/otff),
[cmap](https://learn.microsoft.com/en-us/typography/opentype/spec/cmap),
[hmtx](https://learn.microsoft.com/en-us/typography/opentype/spec/hmtx),
[layout common tables](https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2)
and [GSUB](https://learn.microsoft.com/en-us/typography/opentype/spec/gsub).
