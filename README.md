# OpenType

English | [Français](README.fr.md)

**Pure-object font parsing, substitutions and glyph positioning for PHP 8.3+.**

OpenType belongs to [PDFToolkit](https://github.com/PDFToolkit), while remaining
independent of PDF: installing `pdftoolkit/opentype` installs no PDF package.
Its names and structures follow the [OpenType 1.9.1 specification](https://learn.microsoft.com/en-us/typography/opentype/spec/).

Read a single SFNT font, Unicode character maps and horizontal metrics, then
execute GSUB substitutions and GPOS positioning through immutable script,
language, feature, lookup, anchor and variation objects. Applications choose
features, alternates, glyph order, initial positions and variation instances
explicitly. There is no global registry or hidden file access.

```sh
composer require 'pdftoolkit/opentype:^0.2'
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

The [substitution walkthrough](docs/substitutions.md) explains ligatures, small
capitals and alternates. The [positioning and variation guide](docs/positioning.md)
explains native GPOS, GDEF filtering, device corrections and variable layout. The [architecture](docs/architecture.md),
[coverage](docs/coverage.md), [security policy](SECURITY.md) and
[shared documentation](https://pdftoolkit.github.io/modules/opentype/) describe
the contracts. API pages are generated from the exact published source.

## Scope of 0.2

| Mechanism | Implementation |
| --- | --- |
| Individual SFNT directory | TrueType and OTTO identities, preserved tables, bounded reads and checksum policies |
| Unicode mapping | cmap formats 4/12 and format 14 variation selectors |
| Horizontal metrics | Units per em, advances and left side bearings |
| GSUB | Types 1–8, including contextual/chained formats 1–3 and reverse chaining |
| GDEF | Glyph/mark classes, filtering sets, attachment points, ligature carets and ItemVariationStore |
| GPOS | Types 1–9: single/pair/class kerning, cursive and mark attachments, contextual rules and extensions |
| Layout variations | Conditional feature tables, native region/delta interpolation and signed Device tables |
| Selection | Script, language, required/requested features, alternates, GDEF flags and explicit normalized coordinates |

Full variable-font instantiation (axis normalization, outlines and metric
variation), outline interpretation, font writing and other table semantics
remain pending. Table-byte access does not establish support for those tables.
The engine processes an already ordered glyph sequence; it is not a complete
Unicode shaping or bidirectional text engine. The `0.x` API may evolve between
minor releases.

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
```

The example uses a reproducible font authored for the tests and prints its glyph
substitutions. Independent validators live in `tools/`.
[Release notes](CHANGELOG.md) record each published version.
