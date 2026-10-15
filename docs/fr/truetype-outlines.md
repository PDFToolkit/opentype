# Contours TrueType

[English](../truetype-outlines.md) | Français

`TrueTypeOutlineReader` décode les deux formats `loca` et les enregistrements
`glyf` simples/composites. Il retourne des objets immuables de points, contours,
composants, transformations, limites et octets d’instructions. Le lecteur
n’exécute pas les instructions conservées.

```php
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\QuadraticPathBuilder;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineReader;
use PDFToolkit\OpenType\Value\GlyphId;

$outlines = (new TrueTypeOutlineReader())->read($font);
$outline = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))
    ->outline($outlines, new GlyphId(2));
$path = (new QuadraticPathBuilder())->build($outline);
```

Le moteur assemble les composants non hintés, en
appliquant transformations affines, offsets explicitement mis à l’échelle ou non,
et attachement entre points de contours. Sans drapeau de mise à l’échelle des
offsets, la politique vient de l’appelant : sans mise à l’échelle, avec, ou refus
des transformations ambiguës. Le comportement de plateforme recommandé est sans
mise à l’échelle. Les composants imbriqués suivent l’ordre de la fonte ; les
enfants répétés sont légitimes, les cycles refusés. Des budgets distincts de
profondeur, opérations et points développés empêchent un petit graphe acyclique
d’allouer un contour sans limite.

`QuadraticPathBuilder` convertit les points sur/hors courbe en commandes explicites
de déplacement, ligne, quadratique et fermeture, y compris les milieux implicites
et contours sans point explicitement sur courbe. `GlyphBounds` est le rectangle
déclaré des points de contrôle, pas une affirmation de limite serrée de Bézier,
variée ou hintée.

Exécution des instructions et ajustement à la grille restent distincts.
ROUND_XY_TO_GRID est conservé sans arrondi aux pixels. Les objets optionnels
`GvarInstance` et `PhantomPointProvider` permettent variation native, attachement
aux points fantômes et USE_MY_METRICS par `glyph()` ; voir les
[variations TrueType](truetype-variations.md). `outline()` retourne la géométrie
seule. Un attachement nécessitant des points fantômes échoue explicitement sans
fournisseur. Les glyphes sans données et ceux sans contour mais avec instructions
restent distinguables.

La fixture originale exerce courbes, contours entièrement hors courbe, offsets,
transformations uniformes/matricielles, attachements et imbrications. L’oracle
indépendant compare tous les points des glyphes des fixtures TrueType
originales avec fontTools et leurs géométries quadratiques avec HarfBuzz. Le
décalage d’origine horizontal de HarfBuzz (lsb moins xMin) est pris en compte
explicitement. Les tests natifs couvrent loca court/long, cycles, drapeaux/plages
invalides, instructions et budgets de sortie.

Sources : [glyf](https://learn.microsoft.com/en-us/typography/opentype/spec/glyf),
[loca](https://learn.microsoft.com/en-us/typography/opentype/spec/loca) et
[instructions TrueType et points fantômes](https://learn.microsoft.com/en-us/typography/opentype/spec/tt_instructing_glyphs).
