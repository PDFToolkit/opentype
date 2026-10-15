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

## Implemented mechanisms

| Domain | Supported scope |
| --- | --- |
| Binary values | Bounded big-endian views; typed tags, Unicode scalars and glyph IDs |
| SFNT | Individual TrueType/OTTO directories, table records, preserved bytes, range checks and checksum policies |
| cmap | Base Unicode formats 4/12 and variation sequences format 14 |
| Horizontal metrics | Units per em, advances and left side bearings |
| GSUB structures | Script/language/feature/lookup lists, Coverage 1/2 and referenced indices |
| GSUB type 1 | Single substitution formats 1/2 |
| GSUB types 2/3/4 | Multiple, alternate and ligature format 1 |
| GSUB type 7 | Extension format 1 around supported substitutions |
| GSUB types 5/6/8 | All three contextual/chained formats; reverse chain evaluated from end to start |
| GDEF 1.0/1.2/1.3 | Classes, mark filters/attachment classes, attachment-point lists, caret formats 1–3 and native variation store |
| GPOS types 1/2 | Single formats 1/2, pair format 1 and class-pair format 2 |
| GPOS types 3/4/5/6 | Cursive, mark-to-base, mark-to-ligature and mark-to-mark attachment |
| GPOS types 7/8/9 | Contextual/chained formats 1–3 and positioning extensions |
| Layout variations | Conditional feature tables and ItemVariationStore interpolation at explicit normalized coordinates |
| Device and point records | Signed 2/4/8-bit corrections with explicit pixel scale; retained point indices and explicit hinted-point provider |
| Execution | Explicit/required features, lookup order, alternates, GDEF flags, clusters, ligature association identities and bounded lookup/output/attachment graphs |

Tests cover direct construction and binary input. Independent checks use
fontTools for font data and HarfBuzz for substitutions, positioning, attachments
and variations on reproducible original fonts. The 17 initial, 49 advanced and
36 variable/device cases validate this scope, not every OpenType table. Cursive
checks compare glyph origins and final advance; Device checks permit less than
one design unit for HarfBuzz integer quantization only.

## Remaining work

- User-axis normalization, `fvar`/`avar`/STAT semantics and complete variable-font instantiation, including outline and metric deltas.
- Glyph outlines, TrueType instruction execution and CFF/CFF2 charstrings.
- Naming, OS/2, vertical, baseline, justification, mathematical, color and bitmap semantics.
- Font collections, WOFF/WOFF2, serialization, subsetting and full Unicode shaping.

Uninterpreted tables remain accessible as bytes. Selecting an unsupported
transformation throws explicitly. Coverage advances through coherent releases
with their own tests and independent checks.

`resources/coverage.json` records this scope by table and mechanism. Partial
header reads and raw byte access are distinguished from implemented semantics.
