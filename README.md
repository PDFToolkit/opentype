# OpenType

English | [Français](README.fr.md)

**Pure-object OpenType metadata, variable outlines, metrics and layout for PHP 8.3+.**

OpenType belongs to [PDFToolkit](https://github.com/PDFToolkit), while remaining
independent of PDF: installing `pdftoolkit/opentype` installs no PDF package.
Its names and structures follow the [OpenType 1.9.1 specification](https://learn.microsoft.com/en-us/typography/opentype/spec/).

Read individual TrueType/OTTO fonts, multilingual metadata and horizontal/vertical
metrics; normalize user axes, evaluate variable metrics, assemble quadratic
TrueType outlines and execute cubic CFF/CFF2 programs. GSUB substitutions and
GPOS positioning use immutable script, language, feature, lookup, anchor and
variation objects. Applications explicitly select instances and execution
policies. There is no global registry or hidden file access.

```sh
composer require 'pdftoolkit/opentype:^0.3'
```

PHP 8.3 or newer and a 64-bit platform are required. There are no runtime package
or extension dependencies. CI validates PHP 8.3, 8.4 and 8.5.

## Follow the objects

```php
use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;

// The application supplies bytes; the library does not open a file.
$font = (new SfntReader())->read($fontBytes);
$cmap = (new UnicodeCmapReader())->read($font->table(new Tag('cmap')), $font->glyphCount());
$input = new GlyphSequence([
    $cmap->glyphFor(new CodePoint(0x66)), // f
    $cmap->glyphFor(new CodePoint(0x66)), // f
    $cmap->glyphFor(new CodePoint(0x69)), // i
]);
$gsub = (new GsubReader())->read($font->table(new Tag('GSUB')), $font->glyphCount());
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('liga')]);
$result = (new SubstitutionEngine())->apply($gsub, $selection, $input);
```

The character map first maps Unicode scalars to this font's glyph IDs. The GSUB
reader constructs its layout tables. The engine selects the language system and
features, applies their lookups and returns a new glyph sequence. An `ffi`
ligature is produced only if the font and selection define it. Source clusters
remain available for subsequent text handling.

Guides follow each object contract:

- [Substitutions](docs/substitutions.md) and [positioning](docs/positioning.md).
- [User axes](docs/font-axes.md), [variable metrics](docs/variable-metrics.md),
  [names and OS/2](docs/font-metadata.md), [headers and styles](docs/font-headers-and-styles.md).
- [TrueType outlines](docs/truetype-outlines.md), [gvar/cvar and phantom metrics](docs/truetype-variations.md),
  [CFF/CFF2 outlines](docs/cff-outlines.md), [precomputed device metrics](docs/device-metrics.md).

The [architecture](docs/architecture.md), [coverage](docs/coverage.md),
[security policy](SECURITY.md) and [shared documentation](https://pdftoolkit.github.io/modules/opentype/)
describe the boundaries. API pages are generated from the exact published source.

## Scope of 0.3

| Mechanism | Implementation |
| --- | --- |
| Individual SFNT and cmap | TrueType/OTTO directories, checksums, Unicode 4/12/14 mappings |
| Metadata | Complete head/hhea/maxp/post, name 0/1, OS/2 0–5 and STAT 1.0–1.2 records |
| Axes and metrics | fvar/avar 1.0 normalization, horizontal/vertical metrics, native HVAR/VVAR/MVAR |
| TrueType geometry | Bounded loca/glyf decoding, composite assembly, quadratic paths, native gvar/cvar and explicit phantom metrics |
| CFF/CFF2 geometry | Bounded Type 2 programs and subroutines, cubic paths, explicit matrices, encodings and native blends |
| Layout | GSUB 1–8, GDEF, GPOS 1–9, all contextual formats, native variations and Device corrections |
| Device declarations | gasp recommendations, hdmx widths, LTSH thresholds and VDMX heights with explicit selection |

The [coverage inventory](docs/coverage.md) identifies 27 implemented table scopes,
4 partial tables and 19 pending tables. TrueType instruction execution, CFF hint
application, color/bitmap/baseline/math/justification tables, legacy auxiliary
tables, collections, WOFF/WOFF2, writing and subsetting remain absent. Executing an
unhinted outline or metric instance does not write a static font. Preserved bytes
do not establish semantic support.

The layout engines consume an already ordered glyph sequence. Complete Unicode
shaping, bidirectional layout and rasterization need separate consumers. PDF
embedding and HTML conversion retain their own explicit format contracts. The
`0.x` API may evolve between minor releases.

The supplied [Adobe OTGuide.pdf](resources/reference-documents/OTGuide.pdf) is
retained unchanged as a usage reference. Its original name, 13 pages and SHA-256
are recorded alongside it. Technical definitions use the official specification.
The guide retains its original rights; MIT applies to the package code. The PDF
and development fixtures are excluded from Composer distributions.

## Development

```sh
composer install
composer check
php examples/substitution.php
php examples/manual-ligature.php
php examples/font-metrics.php
php examples/positioning.php
php examples/variable-layout.php
php examples/variable-outlines.php
```

The example uses a reproducible font authored for the tests and prints its glyph
substitutions. Independent validators live in `tools/`.
[Release notes](CHANGELOG.md) record each published version.
