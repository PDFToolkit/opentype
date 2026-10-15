# Positionnement des glyphes et instances variables explicites

[English](../positioning.md) | Français

## Donner des objets propres à chaque étape

La correspondance Unicode produit des identités de glyphes. GSUB peut remplacer
ces identités ; GPOS ajuste ensuite leur placement et leurs avances. Les étapes
sont indépendantes : demander `kern` n'active implicitement ni ligatures, ni
positionnement des marques, ni moteur de script. Fournir les définitions GDEF
de la même fonte aux deux moteurs lorsque les lookups utilisent les classes de
glyphes ou le filtrage des marques.

```php
use PDFToolkit\OpenType\Layout\GdefReader;
use PDFToolkit\OpenType\Metrics\HorizontalMetricsReader;
use PDFToolkit\OpenType\Positioning\GlyphPosition;
use PDFToolkit\OpenType\Positioning\GposReader;
use PDFToolkit\OpenType\Positioning\PositionedGlyphSequence;
use PDFToolkit\OpenType\Positioning\PositioningEngine;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

// $font et $sequence sont les objets explicites des étapes précédentes.
$definition = (new GdefReader())->read($font->table(new Tag('GDEF')), $font->glyphCount());
$gpos = (new GposReader())->read($font->table(new Tag('GPOS')), $font->glyphCount());
$metrics = (new HorizontalMetricsReader())->read($font);
$initial = new PositionedGlyphSequence($sequence, array_map(
    fn (GlyphId $glyph): GlyphPosition => new GlyphPosition($metrics->advanceWidth($glyph)),
    $sequence->glyphs,
));
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('kern')]);
$positioned = (new PositioningEngine())->apply($gpos, $selection, $initial, $definition);
```

`GlyphPosition` contient `xAdvance`, `yAdvance`, `xOffset` et `yOffset` en unités
de dessin. L'application fournit les métriques initiales et choisit la politique
d'avance des marques. Le placement agit sur le glyphe courant ; l'avance agit
sur l'origine suivante. Le moteur renvoie une nouvelle `PositionedGlyphSequence`
immuable et préserve son entrée.

## Lire et exécuter GPOS

Les types 1 à 9 sont natifs : ajustements simples et par paire, matrices de
classes, attaches cursives entrée/sortie, marque-base, marque-ligature,
marque-marque, positionnement contextuel et contextuel chaîné dans leurs trois
formats, et extensions. Les fonctionnalités obligatoires et demandées suivent
l'ordre des lookups. La première sous-table correspondante l'emporte ; les
actions contextuelles s'appliquent uniquement aux positions qu'elles désignent.

Les attaches conservent leur relation au parent pendant les ajustements
ultérieurs de placement et d'avance. `RIGHT_TO_LEFT` choisit l'ancrage transversal
d'une chaîne cursive ; il ne choisit pas le sens Unicode du texte.
`PositioningContext(vertical: true)` sélectionne les ajustements d'avance
verticale. Ordre bidirectionnel, analyse des scripts, normalisation, coupure des
lignes et métriques verticales initiales relèvent de l'application ou d'un moteur
de shaping distinct.

Les identités `LigatureAssociation` produites par GSUB conservent le bon composant
pour chaque marque, même lorsque les clusters de l'application sont identiques.
Une application construisant directement des ligatures peut fournir ces
associations ; sans association correspondante, une marque utilise le dernier
composant conformément à OpenType. Provenance des clusters et identité
d'attachement sont des objets distincts.

## Devices, points de contour et curseurs

`PositioningContext` rend explicites les unités par em et la taille optionnelle
en pixels par em. Les formats Device 1/2/3 décodent des corrections signées de
2/4/8 bits en pixels. Sans taille en pixels, la correction vaut zéro. Les
résultats conservent les fractions d'unité de dessin ; la sortie entière de
HarfBuzz peut différer de moins d'une unité lorsqu'une correction en pixels ne
se divise pas exactement à l'échelle choisie.

Le format Anchor 2 conserve son indice de point et ses coordonnées de dessin.
Un `AnchorPointResolver` explicite peut fournir un point de contour après
hinting. Sans ce fournisseur, les coordonnées de dessin restent utilisables.
Un curseur GDEF défini uniquement par un indice de point n'a pas de coordonnées
de repli et exige cette capacité. Aucun bytecode de fonte ne s'exécute
implicitement. `GdefTable` expose les listes de points d'attache et les curseurs
de ligature sous forme d'enregistrements typés.

## Variations natives du layout

`NormalizedCoordinates` exprime les coordonnées dans l'ordre des axes et conserve
la précision signée 2.14 requise après la conversion 16.16 d'OpenType. La
normalisation des valeurs utilisateur et la transformation `avar` utilisent les
[objets d’axes distincts](font-axes.md) ; cette API ne devine pas l'instance. Fournir les coordonnées à
`FeatureSelection` pour évaluer les FeatureVariations GSUB/GPOS. Les conditions
suivent leur ordre de priorité ; le premier enregistrement admissible remplace
les tables de fonctionnalités. Une condition future inconnue ou une version de
table de remplacement non prise en charge fait échouer cet enregistrement et
permet d'essayer le suivant.

GDEF 1.3 contient un `ItemVariationStore` natif. `VariationInstance` évalue ses
régions et ses deltas signés une fois, puis répond aux `VariationIndex` sans
répéter l'interpolation pour chaque glyphe :

```php
use PDFToolkit\OpenType\Positioning\PositioningContext;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;
use PDFToolkit\OpenType\Variation\VariationInstance;

$coordinates = new NormalizedCoordinates([0.5]); // Instance explicite à un axe.
$selection = new FeatureSelection(new Tag('latn'), features: [new Tag('kern')], coordinates: $coordinates);
$instance = new VariationInstance($definition->itemVariationStore, $coordinates);
$context = new PositioningContext($metrics->unitsPerEm, variations: $instance);
$positioned = (new PositioningEngine())->apply($gpos, $selection, $initial, $definition, $context);
```

Vérifier la présence de `itemVariationStore` avant de construire l'instance. Un
VariationIndex effectivement utilisé sans fournisseur de deltas provoque une
erreur explicite. La variation du layout reste distincte des [instances métriques](variable-metrics.md),
des [variations TrueType](truetype-variations.md) et des [contours CFF2](cff-outlines.md).
Ces objets explicites n'écrivent pas un fichier de fonte statique.

## Exécuter les exemples et contrôles indépendants

Depuis un clone du dépôt :

```sh
php examples/positioning.php
php examples/variable-layout.php
python tools/build_advanced_layout_fixture.py --check
python tools/build_variation_fixture.py --check
python tools/validate_advanced_layout.py --php php
python tools/validate_variations.py --php php
```

La paire `a,b` de la fonte originale passe des avances `[600,600]` à `[530,600]`.
L'exemple variable calcule `600 + 10 + 100 × 0.5 = 660`. Les tags synthétiques
comme `sng2` appartiennent à cette fixture ; une application choisit les tags de
ses propres fontes. Les fixtures et outils de test sont exclus des archives
Composer ; fournir un chemin de fonte de l'application hors d'un clone.

Les oracles indépendants couvrent tous les formats contextuels, le sens des
substitutions inverses, le filtrage GDEF, les attaches, tous les formats Device
et sept instances normalisées. Ils valident ce périmètre, pas toutes les tables
OpenType.

Sources : [GPOS](https://learn.microsoft.com/en-us/typography/opentype/spec/gpos),
[GDEF](https://learn.microsoft.com/en-us/typography/opentype/spec/gdef),
[tables de layout](https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2)
et [magasins de variations](https://learn.microsoft.com/en-us/typography/opentype/spec/otvarcommonformats).
