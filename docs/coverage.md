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
| Execution | Explicit and required features, lookup order, alternate policy, clusters and bounded output; zero lookup flags |

Tests cover direct construction and binary input. Independent checks use
fontTools for font data and HarfBuzz for supported substitutions on reproducible
fonts. They validate this tested scope, not every OpenType table.

## Remaining work

- Contextual, chained contextual and reverse chained GSUB types 5/6/8.
- GDEF classes, mark filtering/attachment and flags requiring those semantics.
- GPOS positioning, including kerning and mark/cursive attachment.
- Variable-font axes, interpolation and feature variations.
- Glyph outlines, TrueType instruction execution and CFF/CFF2 charstrings.
- Naming, OS/2, vertical, baseline, justification, mathematical, color and bitmap semantics.
- Font collections, WOFF/WOFF2, serialization, subsetting and full Unicode shaping.

Uninterpreted tables remain accessible as bytes. Selecting an unsupported
transformation throws explicitly. Coverage advances through coherent releases
with their own tests and independent checks.

`resources/coverage.json` records this scope by table and mechanism. Partial
header reads and raw byte access are distinguished from implemented semantics.
