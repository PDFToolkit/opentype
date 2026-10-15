# Variations des contours TrueType et du CVT

[English](../truetype-variations.md) | Français

`GvarReader` lit la version 1.0 depuis un SFNT explicite et ses `TrueTypeOutlines` déjà décodés. Il vérifie le nombre d’axes `fvar` et de glyphes `maxp`/contours, les deux largeurs d’offsets, les tuples de pic partagés ou embarqués, les régions intermédiaires, les listes de points partagées/privées et les suites de deltas octets/mots/zéros. Les frontières des en-têtes et données, les enregistrements développés et le travail d’exécution sont bornés.

```php
use PDFToolkit\OpenType\Outline\CompositeOffsetPolicy;
use PDFToolkit\OpenType\Outline\MetricPhantomProvider;
use PDFToolkit\OpenType\Outline\TrueTypeOutlineEngine;
use PDFToolkit\OpenType\Variation\GvarInstance;
use PDFToolkit\OpenType\Variation\GvarReader;

$gvar = (new GvarReader())->read($font, $outlines);
$instance = new GvarInstance($gvar, $normalizedCoordinates);
$provider = new MetricPhantomProvider($horizontalMetrics, $verticalMetrics);
$glyphOutline = (new TrueTypeOutlineEngine(CompositeOffsetPolicy::Unscaled))
    ->glyph($outlines, $glyph, $provider, $instance);
$path = $glyphOutline->outline;
$advanceWidth = $glyphOutline->phantoms->advanceWidth();
```

L’instance calcule le scalaire de chaque tuple, déduit les deltas omis des glyphes simples à partir des coordonnées originales des contours, puis somme les deltas pondérés. Un contour sans référence reste inchangé ; une référence unique translate tout le contour. Des références de coordonnées identiques et de deltas différents donnent un delta déduit nul. Les points fantômes ne reçoivent jamais de deltas déduits. Les pseudo-points composites ajustent uniquement le placement XY, avant l’éventuelle transformation des offsets ; les composants attachés par points ignorent leurs deltas de pseudo-points. Les enfants sont instanciés récursivement, sans mutation des objets sources.

Quatre points fantômes restent distincts des points dessinables. `MetricPhantomProvider` déduit leurs valeurs par défaut des métriques horizontales explicites, des métriques verticales optionnelles et des bornes déclarées du glyphe. Sans métriques verticales, ses points fantômes verticaux ont des valeurs par défaut nulles. `glyph()` applique les deltas X horizontaux et Y verticaux ; le dernier composant portant `USE_MY_METRICS` sélectionne les points fantômes sans hinting de son enfant. L’attachement des composants par points fantômes est disponible lorsqu’un fournisseur est passé. `outline()` ne nécessite aucun fournisseur et renvoie uniquement la géométrie.

La sélection des métriques est explicite : ce chemin fournit le recours aux points fantômes gvar ; ne pas appliquer en plus les deltas HVAR/VVAR à ces avances. La bibliothèque ne décide pas silencieusement entre ces voies et n’arrondit pas les unités de dessin en pixels. `GvarInstance::deltas()` expose aussi l’interpolation des tuples séparément pour les applications disposant de leur propre chaîne de points et métriques.

`ControlValueReader` lit chaque FWORD signé de `cvt `. `CvarReader` lit son magasin de variations par tuples version 1.0, avec les pics embarqués obligatoires. `CvarTable::instance($defaults, $coordinates)` renvoie des valeurs de dessin immuables. Les indices CVT absents conservent leurs valeurs par défaut ; cvar ne réalise aucune interpolation de contours. Les fractions restent conservées ; mise à l’échelle du périphérique et arrondi aux pixels appartiennent au moteur d’instructions.

Il s’agit d’une exécution de variations sans hinting. Les octets d’instructions `fpgm`, `prep` et des glyphes ne sont pas exécutés ; aucune VM TrueType ni rastérisation n’est annoncée. Les bornes par défaut des glyphes restent les métadonnées sources ; les bornes d’un tracé instancié se déduisent de sa géométrie résultante. L’interpolation des tuples clairsemés est distincte de l’exécution de l’instruction TrueType IUP.

La fixture originale à deux axes inclut contours simples clairsemés, contours entièrement hors courbe, composants mis à l’échelle ou attachés par points, composites imbriqués, avances fantômes et cvar. L’oracle indépendant compare 225 instances de glyphes, 100 valeurs CVT et six cas `USE_MY_METRICS` avec fontTools/HarfBuzz. Les tests couvrent aussi les deux largeurs d’offsets, suites malformées, tuples absents, bornes, budgets, cas limites des contours et attachements fantômes.

Sources : [gvar](https://learn.microsoft.com/en-us/typography/opentype/spec/gvar), [cvar](https://learn.microsoft.com/en-us/typography/opentype/spec/cvar), [formats communs de variations](https://learn.microsoft.com/en-us/typography/opentype/spec/otvarcommonformats), [vue d’ensemble des variations](https://learn.microsoft.com/en-us/typography/opentype/spec/otvaroverview).
