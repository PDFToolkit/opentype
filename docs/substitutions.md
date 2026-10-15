# Ligatures, small capitals and alternate glyphs

English | [Français](fr/substitutions.md)

## Unicode first, glyphs second

A letter is not a glyph ID. The same Unicode scalar can have different glyph IDs
in different fonts; a ligature can represent several characters with one glyph.
Resolve `CodePoint` objects with `UnicodeCharacterMap`, then construct a
`GlyphSequence`.

The [README example](../README.md#follow-the-objects) starts with `f`, `f`, `i`,
obtains their glyph IDs from cmap and requests the Latin `liga` feature. If the
font defines the ligature for this selection, the result contains its glyph and
retains the source cluster origin.

## Selecting the feature

`FeatureSelection` describes the script, language system, requested features and
fallback choices. The default-language system belongs to a script. OpenType
language tags are font layout identifiers, not locales such as `fr-FR`.

A feature tag describes a purpose; its lookups define the actual transformation.
`smcp` selects small capitals only if the font supplies that feature. It does not
geometrically shrink capitals.

```php
$smallCapitals = new FeatureSelection(new Tag('latn'), features: [new Tag('smcp')]);
$result = (new SubstitutionEngine())->apply($gsub, $smallCapitals, $input);
```

The engine includes the chosen language system's required feature. Each selected
lookup runs in lookup-list order, once even if several features reference it.
Within a lookup, the first matching subtable supplies the transformation.

## Choosing an alternate

`AlternateIndex` makes the application's choice explicit, using a one-based
index. There is no random or global alternate preference.

```php
use PDFToolkit\OpenType\Substitution\AlternateIndex;

$alternates = new FeatureSelection(
    new Tag('latn'),
    features: [new Tag('salt')],
    alternateSelector: new AlternateIndex(1),
);
$result = (new SubstitutionEngine())->apply($gsub, $alternates, $input);
```

The feature must exist and use supported lookups. An absent requested feature
does not create glyphs. An unsupported selected lookup fails explicitly, and a
selected alternate substitution needs an alternate selector.

## Inspecting the result

From a source checkout:

```sh
composer install
php examples/substitution.php
php examples/manual-ligature.php
```

The example demonstrates `liga`, `smcp`, `salt` and multiple substitution using
the reproducible test font. Output contains glyph IDs and source clusters; the
independent validator compares supported transformations with HarfBuzz. These
are font-format results without a PDF rendering step.

The [manual construction example](https://github.com/PDFToolkit/opentype/blob/v0.2.0/examples/manual-ligature.php)
builds Coverage, LigatureSet, LookupList, FeatureList and ScriptList directly.
Its illustrative identities are `f=4`, `i=5`, `ffi=7`: input `[4,4,5]` becomes
`[7]` with source cluster `[0]`. This path uses the same engine without parsing
a font. The [font metrics example](https://github.com/PDFToolkit/opentype/blob/v0.2.0/examples/font-metrics.php)
separately explains Unicode mapping, design units and variation selectors.

Contextual GSUB types 5/6 execute all three glyph/class/coverage formats; type 8
runs from the end of the logical sequence and can observe later replacements.
Nested lookups have their own filtering flags and explicit depth/operation
budgets. GDEF filtering preserves skipped marks and ligature component identity.

[GPOS positioning](positioning.md) is an explicit next stage. Line layout, PDF
encoding and complete script shaping still need further processing; selecting
glyphs alone does not produce finished page text.

See [coverage](coverage.md), [GSUB](https://learn.microsoft.com/en-us/typography/opentype/spec/gsub)
and the [feature registry](https://learn.microsoft.com/en-us/typography/opentype/spec/featurelist).
