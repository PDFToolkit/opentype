# OpenType 1.9.1 implementation coverage

English | [Français](fr/coverage.md)

## Technical source and usage guide

The technical source is [OpenType 1.9.1](https://learn.microsoft.com/en-us/typography/opentype/spec/).
Its version is separate from the package version. The supplied Adobe
[OTGuide.pdf](../resources/reference-documents/OTGuide.pdf) is an October 2008
usage guide of 13 physical pages. It illustrates ligatures, small capitals and
alternates, rather than defining their binary records.

The original guide is preserved: **826,379 bytes**, SHA-256
`39bc32fa334c76d488312ef92c16a5e147315d1c73e8b6ead77f410d297739f4`.
Provenance is recorded in `resources/reference-documents/metadata.json`.
The PDF is excluded from Composer archives.

## Implemented mechanisms in 0.3

The machine-readable [coverage manifest](../resources/coverage.json) inventories
50 OpenType tables: 27 implemented within the versioned scope below, 4 partial,
and 19 pending. An implemented table model does not imply every consumer policy
or a complete font renderer.

| Domain | Supported scope |
| --- | --- |
| Binary and SFNT | Bounded views, typed identities, individual TrueType/OTTO directories, table records and checksum policies |
| cmap (partial) | Unicode formats 4/12 and variation sequences format 14 |
| head, hhea, maxp, post | Complete declared fields; maxp 0.5/1.0 and post 1/2/2.5/3, including glyph names |
| name, OS/2 | Name 0/1 records and language tags; explicit Unicode/ASCII/Macintosh Roman decoding; OS/2 0–5 fields and embedding flags |
| hmtx, vhea, vmtx, VORG | Horizontal/vertical advances and bearings, versioned vertical header and CFF origins |
| fvar, avar, STAT | Ordered axes/named instances; explicit user normalization and avar 1.0 maps; STAT 1.0–1.2 values in all four formats |
| HVAR, VVAR, MVAR | Native region/delta interpolation, bounded index maps and explicit metric adjustment |
| loca, glyf (partial) | Both loca formats; simple/composite outlines; unhinted assembly, affine transforms, point attachment, quadratic paths and explicit phantom metrics |
| gvar, cvar, cvt | Native tuple decoding, sparse contour interpolation, composite/phantom deltas and unscaled CVT variation |
| CFF, CFF2 (partial) | Bounded INDEX/DICT/FDSelect, Type 2 paths and subroutines, explicit matrices, CFF charsets/encodings/composites and CFF2 blends |
| GSUB | Types 1–8, all contextual/chained formats, script/language/feature selection and native feature variations |
| GDEF | Versions 1.0/1.2/1.3: classes, filtering, attachment points, caret formats 1–3 and native variation store |
| GPOS | Types 1–9, all contextual/chained formats, pair/class adjustments, cursive/mark attachment and native feature variations |
| Device corrections | Signed 2/4/8-bit values with explicit scale; hinted point indices require an explicit provider |
| gasp, hdmx, LTSH, VDMX | Versioned rendering recommendations, precomputed pixel metrics, thresholds and explicit size/aspect-ratio selection |
| Execution budgets | Aggregate records/bytes, output points/glyphs, operations, nested lookup/component/subroutine depth and cycles |

Readers return immutable objects. Execution receives explicit features, alternates,
axis coordinates, pixel scales, metric providers and initial positions. Mutable
buffers are limited to a single read or execution.

## Remaining table work

These **19 tables remain uninterpreted** beyond their bounded SFNT bytes:

| Group | Tables | Missing work and dependencies |
| --- | --- | --- |
| TrueType programs | `fpgm`, `prep` | Instruction models and an execution VM; shared graphics state, CVT/storage, stack and bounded calls |
| Baseline, justification, mathematics | `BASE`, `JSTF`, `MATH` | Table models and explicit consumers; reuse layout coverage, device/variation records and bounded lookups |
| Layered color | `COLR`, `CPAL` | Palette/version models, paint graph, bounded cycles and variation evaluation; actual painting is separate |
| SVG and bitmap glyphs | `SVG `, `EBDT`, `EBLC`, `EBSC`, `CBDT`, `CBLC`, `sbix` | Glyph ranges, strikes, indices, metrics and payload models; codec/rendering capabilities remain explicit |
| Legacy and auxiliary | `kern`, `MERG`, `meta`, `PCLT`, `DSIG` | Versioned table models and their consumers; DSIG packet parsing alone would not verify signatures or trust |

The four **partial tables** have these precise boundaries:

- `cmap`: formats 0/2/6/8/10/13 and legacy platform mapping policies are absent.
- `glyf`: contour geometry, composite variation and phantom metrics execute;
  retained instruction bytes do not run. Grid fitting and pixel rounding need a VM.
- `CFF `: supported Type 2 geometry executes, including arithmetic, subroutines
  and deprecated composites. Applying hints and interpreting every high-level
  DICT metadata field are absent; legacy Type 1 charstrings and synthetic/MM font
  mechanisms are outside this reader's supported CFF contract.
- `CFF2`: cubic geometry, charstring blends and Private DICT blends execute.
  Hint application and rasterization are absent; retaining DICT operands does not
  implement every metadata consumer.

`avar` version 2 is unsupported. Locale/BCP 47 registry matching, automatic style
naming and PostScript instance-name generation are not provided by `name`/STAT.
Default metadata are never silently rewritten by an instance: callers explicitly
apply MVAR/HVAR/VVAR or gvar phantom results, avoiding double application.

## Containers and separate application responsibilities

TTC/OTC collections, WOFF/WOFF2 decoding, font serialization, subsetting and writing
a static variable-font instance remain pending. Evaluating an outline or metric
instance does not produce a rewritten font file. Container support needs bounded
shared-table offsets and explicit decompression; writing needs rebuilt dependent
tables and checksums, and subsetting needs glyph/lookup/variation closure.

The library does not provide a rasterizer, complete Unicode script shaping,
bidirectional layout, line breaking, system font discovery, locale/font fallback,
font licensing decisions or signature trust policy. Such consumers may use these
objects, but require their own explicit algorithms and capabilities. The existing
PDF adapter's supported format list is independent: this release does not silently
extend PDF embedding or HTML shaping.

Unsupported selected mechanisms fail explicitly. Raw table bytes and opaque
instruction/payload bytes are never counted as implemented algorithms.

## Validation evidence

Native tests cover constructed objects, valid binary fonts, truncation, malformed
references, shared offsets, cycles and aggregate budgets. Original MIT fixtures
are reproducible byte for byte. Independent fontTools comparisons check every
modeled field, contours, names and variation deltas; HarfBuzz compares selected
GSUB/GPOS results, normalized axes, metrics and quadratic/cubic drawn geometry.
The oracle for precomputed device metrics checks declarations, not rasterization.

Oracles explicitly account for HarfBuzz origin conventions and integer
quantization where documented. Those bounded tolerances do not replace strict
comparisons for identities, records or exactly representable values. Release
qualification runs the native suite, static analysis, formatting, Composer checks,
reference identity, all fixture rebuilds and independent oracles on PHP 8.3/8.4/8.5.
