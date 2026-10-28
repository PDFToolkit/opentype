# Collections, character maps and feature parameters

English | [Français](fr/collections-and-feature-parameters.md)

## Explicit collection selection

```php
use PDFToolkit\OpenType\Sfnt\CollectionReader;
use PDFToolkit\OpenType\Sfnt\SfntSerializer;

$collection = (new CollectionReader())->read($collectionBytes);
$font = $collection->font(1);
$standaloneBytes = (new SfntSerializer())->write($font);
```

TTC/OTC versions 1.0 and 2.0 can contain TrueType and CFF faces. Directory offsets
and shared table offsets refer to the collection bytes. The reader checks every
face, bounds, checksums and aggregate directory budget; exact shared ranges are
allowed, partial overlaps and directory/table collisions are rejected. An absent
face index fails; no face is silently selected. Version-2 DSIG bytes are bounded
and exposed as opaque data, without signature parsing or trust verification.

`SfntSerializer` writes one complete selected face, rebuilding its sorted
directory, padding and checksums. It preserves table/glyph content except for
`head.checkSumAdjustment` and the invalidated DSIG, which is discarded. It does
not create a subset, modify variation tables, freeze a variable instance or
write a collection. Re-serialization is not a proof that every retained table's
semantics are implemented.

## Complete Unicode cmap format family

`UnicodeCmapReader` supports base formats 0/2/4/6/8/10/12/13 and format-14 variation
sequences. Format 2 uses bounded subheaders, relative glyph arrays and nonzero
modulo deltas. Format 8 validates the mixed-width bitmap and groups. Formats
12/13 retain compact groups rather than allocating a map for every scalar.
Format 13 is a last-resort constant-glyph mapping and requires platform 0/encoding 6.

Selection remains Unicode: Windows 3/1 requires format 4, Windows 3/10 requires
format 12; the reader prefers a supported full-repertoire Unicode base map and
does not merge subtables. It does not reinterpret legacy Macintosh/Windows byte
encodings as Unicode. Records are ordered by platform, encoding **and language**;
multiple distinct Macintosh languages may coexist with a selected Unicode map.
The remaining partial cmap scope is legacy encoding policy/transcoding.

## Semantic feature parameters

`Feature::$parameters` exposes `OpticalSizeParameters`, `StylisticSetParameters`
or `CharacterVariantParameters` for `size`, `ss01`–`ss20` and `cv01`–`cv99`.
The existing raw `$featureParams` remains available. Name IDs stay explicit;
resolve them through `NameTable` using an application-selected language.

```php
use PDFToolkit\OpenType\Layout\OpticalSizeFamily;
use PDFToolkit\OpenType\Layout\OpticalSizeParameters;

$caption = new OpticalSizeParameters(80, 1, 256, 60, 100);
$text = new OpticalSizeParameters(120, 1, 256, 100, 180);
$family = new OpticalSizeFamily('Example Regular', [$caption, $text]);
$selected = $family->select(12.0); // $text
```

The five `size` fields are decipoints, not pixels or variable `opsz` coordinates.
The lower size boundary is exclusive and the upper inclusive. Subfamily ID zero
means the other grouping fields are ignored, while design size remains useful.
A family receives explicit members with one nonzero subfamily ID and nonoverlapping
ranges; it selects a containing range, otherwise the closest range, choosing the
higher range on a tie. The caller identifies the actual font associated with each
returned parameter object; no system font search or synthetic optical scaling occurs.

Stylistic-set parameters expose their UI name ID. Character-variant parameters
expose label/tooltip/sample IDs, named choices and Unicode scalars, and resolve a
one-based parameter value to its name ID. Reading labels does not activate a
feature, choose an alternate, draw a palette or perform Unicode case conversion.
`size` is a GPOS metadata feature with zero lookups; it is not glyph positioning.

## Feature ranges without cutting context

```php
use PDFToolkit\OpenType\Substitution\FeatureRange;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\Tag;

$selection = new FeatureSelection(new Tag('latn'), ranges: [
    new FeatureRange(new Tag('liga'), 3, 8),
    new FeatureRange(new Tag('liga'), 5, 6, enabled: false),
]);
```

Ranges use original source-cluster values, with an inclusive start and exclusive
end. Features in `features` are enabled globally; a range can enable an otherwise
unselected feature or disable part of a global feature. Later ranges for the same
tag override earlier ones. A required LangSys feature remains required.
Lookups shared by several features run once in lookup order wherever any of their
masks is enabled. The range mask is inherited through multiple substitutions and
from the first consumed glyph of a ligature; `featureClusters` retains this
identity separately from merged extraction clusters. When input clusters are
Unicode-scalar positions, range indices are scalar positions, not UTF-8 byte offsets.

Both engines keep the whole buffer: contextual backtrack/lookahead remain visible
outside the selection. All matched input glyphs of a context/ligature, both glyphs
of a pair/cursive attachment, and both mark/target of a mark attachment must be
selected for that lookup. GDEF-ignored marks remain ignored. Range inspection
consumes the operation budget. `validate_feature_ranges.py` independently compares
these rules with HarfBuzz, including overlapping overrides, expansion, all chained
context formats, reverse substitution and ranges around ligatures/positioning.
A boolean mask chooses activation; alternate values still use the explicit
`AlternateSelector` policy. Script itemization and bidi remain separate.

## Guide coverage and boundaries

The unchanged Adobe [OTGuide.pdf](../resources/reference-documents/OTGuide.pdf)
uses physical pages 1–13:

| Pages | Usage | Implemented mechanism or application boundary |
| --- | --- | --- |
| 1 | Contents | Document navigation |
| 2 | TrueType/CFF, otf/ttf/ttc, characters/glyphs | Individual fonts, TTC/OTC, cmap and both outline models |
| 3 | Optical caption/text/subhead/display faces | Explicit `size` parameters/family selection; OS installers and management external |
| 4 | Installation | Application/operating system, no hidden file writes |
| 5–6 | Unicode input, pi fonts, keyboards, unencoded glyphs | Unicode maps and explicit GIDs; keyboard/import/transcoding policy external |
| 7 | One/many substitutions and positioning | GSUB 1–8, GPOS 1–9 and source clusters; script stage order explicit |
| 8 | Activate features on selected text | Explicit run/stage feature selection; source-cluster range masks retain full-run context |
| 9–10 | Capitals, ligatures, fractions, swashes, alternates | Font-defined GSUB/GPOS lookups and alternate choice; no synthesized designs or automatic casing/fraction detection |
| 11 | Cumulative stylistic sets, figures, marks, contextual forms | Multiple feature selection and mark/contextual execution; ssXX labels; Unicode joining/itemization and bidi external |
| 12 | Glyph/alternate palette and font selection | GID, names, alternate records, ssXX/cvXX parameters and STAT; UI external |
| 13 | Application availability table | Selectable feature tags; the `kern` feature is not the unrelated legacy `kern` table |

This is a usage guide, not a claim that every script, GUI or OpenType table is
implemented. [Coverage](coverage.md) retains the real pending table inventory.
`avar` version 2 remains an extension proposal outside the published 1.9.1
specification used here. No implementation is claimed by merely retaining bytes.

## Independent checks

`tools/validate_cmaps.py` compares six additional formats with FreeType's public
character-map API. `validate_collections.py` compares every face/table with
fontTools TTCollection and reopens serialized faces with strict checksums.
`validate_feature_parameters.py` compares all modeled parameter fields with
fontTools. `build_collection_fixtures.py --check` rebuilds both original MIT
collections. These checks run in the PHP 8.3/8.4/8.5 matrix; FreeType is a test-only
system dependency. They do not verify DSIG trust or a font rasterizer.

Technical sources: [SFNT collections](https://learn.microsoft.com/en-us/typography/opentype/spec/otff),
[cmap](https://learn.microsoft.com/en-us/typography/opentype/spec/cmap),
[size and stylistic sets](https://learn.microsoft.com/en-us/typography/opentype/spec/features_pt),
[character variants](https://learn.microsoft.com/en-us/typography/opentype/spec/features_ae).
