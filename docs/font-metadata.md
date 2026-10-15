# Names and OS/2 metadata

English | [Français](fr/font-metadata.md)

`NameReader` models both versions of the naming table. Every record retains its
platform, encoding, language and name IDs and original bytes. Version 1 language
tags are decoded from UTF-16BE. An absent language-tag index makes that record
ineligible for selection, without replacing it with a guessed language.

```php
use PDFToolkit\OpenType\Names\NameReader;
use PDFToolkit\OpenType\Names\UnicodeNameDecoder;
use PDFToolkit\OpenType\Metrics\Os2Reader;
use PDFToolkit\OpenType\Value\Tag;

$names = (new NameReader())->read($font->table(new Tag('name')));
$records = $names->select(6, 3, 1, 0x0409);
$postScriptName = $records === [] ? null : $records[0]->text(new UnicodeNameDecoder());
$metrics = (new Os2Reader())->read($font->table(new Tag('OS/2')));
$permission = $metrics->embeddingRights->permission();
```

Selection is exact and explicit. `UnicodeNameDecoder` handles Unicode/Windows
Unicode UTF-16BE and ISO ASCII/10646; malformed surrogate pairs fail without
replacement characters. `MacintoshRomanNameDecoder` handles Macintosh Roman.
Other legacy/custom encodings require an application `NameDecoder`. Retaining
records does not implement every platform codec, BCP 47 registry matching,
font-picker naming heuristics or locale fallback.

`Os2Table` exposes every field in versions 0–5 as typed immutable values, including
PANOSE, Unicode/codepage range bits, classification, sub/superscript and strikeout
metrics, typographic/Windows metrics, versioned height/default/context fields and
optical point ranges. Missing fields are `null`; the shortened 68-byte legacy
version 0 is recognized. Extra bytes beyond the declared version are ignored.
These are declared metadata, not proof of character coverage, a fallback-font
matching algorithm or actual glyph advances. Variable MVAR deltas are a separate
mechanism.

`EmbeddingRights` applies the version-specific fsType meaning: versions 0–1
ignore the later no-subsetting/bitmap-only flags; versions 0–2 interpret combined
usage bits by the permitted least-restrictive rule; versions 3–5 reject mutually
exclusive usage combinations. Reading these declarations neither embeds a font
nor grants independent licensing rights. `useTypographicMetrics()` exposes the
version-4-or-later selection flag. Optical ranges use twentieths of points, lower
inclusive and upper exclusive, with 65535 representing infinity.

Readers check storage boundaries, sorted identities, versioned lengths and
aggregate string byte/record limits before copying. Repeated references consume
the aggregate budget even when they alias the same stored bytes. The independent
fontTools oracle compares every name record and OS/2 field in the original
fixtures and all six table versions. Native tests also cover name version 1, supplementary
Unicode, legacy OS/2 and malformed data.

Technical sources: [name](https://learn.microsoft.com/en-us/typography/opentype/spec/name)
and [OS/2](https://learn.microsoft.com/en-us/typography/opentype/spec/os2).
