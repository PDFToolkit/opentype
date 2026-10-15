# En-têtes, noms de glyphes et styles

[English](../font-headers-and-styles.md) | Français

Ces lecteurs explicites renvoient des objets indépendants et immuables :

| Lecteur | Champs et comportement |
| --- | --- |
| `FontHeaderReader` | Tous les champs `head` 1.0, dates signées sur 64 bits en secondes depuis 1904 UTC, révision fixe et bornes déclarées des points de contrôle |
| `HorizontalHeaderReader` | Toutes les métriques, champs du curseur et compteurs `hhea` 1.0 ; vérification des champs réservés |
| `MaximumProfileReader` | Toutes les déclarations `maxp` 1.0 ; 0.5 fournit uniquement le nombre de glyphes, sans inventer de limites TrueType |
| `PostScriptReader` | En-tête, noms standard version 1, noms Pascal indexés version 2, offsets historiques 2.5 et absence de noms version 3 |
| `GaspReader` | Versions 0/1, intervalles de ppem ordonnés et recommandations explicites de rastérisation |
| `StyleAttributesReader` | STAT 1.0/1.1/1.2, axes avec ordre et références de noms, les quatre formats de valeurs d’axes et l’identifiant de nom de repli |

```php
use PDFToolkit\OpenType\Metadata\StyleAttributesReader;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;

$fvar = (new FvarReader())->read($font->table(new Tag('fvar')));
$styles = (new StyleAttributesReader())->read($font->table(new Tag('STAT')), $fvar);
$matches = $styles->matching(new UserCoordinates(['wght' => 700, 'wdth' => 81.25]));
```

L’ordre des axes STAT est indépendant de fvar. Passer un modèle fvar vérifie que chaque axe dynamique possède un axe STAT de même identifiant de nom. Les tailles étendues des enregistrements d’axes sont respectées. `matching()` renvoie tous les enregistrements point/intervalle/lien/combinaison correspondants dans l’ordre source ; les bornes des intervalles sont inclusives, les valeurs liées restent des métadonnées de liaison des styles, et aucune coordonnée utilisateur manquante n’est fournie implicitement. Décodage des noms, priorité du format 4 lors de la composition, localisation et projection dans les familles R/B/I/BI ou WWS restent des politiques de l’application. Les indicateurs et valeurs liées restent accessibles explicitement.

Les noms de glyphes post 2 sont des chaînes Pascal bornées de 1–63 caractères ASCII autorisés. La version 1 exige l’ordre standard complet de 258 glyphes ; la version 3 n’expose aucun nom. Un nom de glyphe n’est pas une correspondance Unicode. Les dates signées originales de head sont conservées sans conversion flottante ni sélection implicite de fuseau. Les champs d’en-têtes sont des déclarations : ni les bornes `head` ni les limites `maxp` ne prouvent que tous les contours et instructions ont été validés par rapport à elles. Les budgets d’exécution restent contrôlés par l’application.

`gasp->atPpem($ppem)` sélectionne une recommandation sans activer de rastériseur. Les ajustements MVAR des en-têtes, du soulignement ou de gasp restent explicites via l’API de variations métriques. Lire `gasp` n’applique ni ajustement à la grille, ni anticrénelage, ni ClearType.

L’oracle indépendant compare chaque champ à fontTools sur toutes les fixtures et les variantes STAT 1.0/1.1/1.2. Les cas natifs couvrent aussi les limites des dates signées sur 64 bits, post 1/2/2.5/3, les bornes Pascal, les indicateurs versionnés de gasp, les extensions de taille des axes STAT et les offsets relatifs malformés.

Sources : [head](https://learn.microsoft.com/en-us/typography/opentype/spec/head), [hhea](https://learn.microsoft.com/en-us/typography/opentype/spec/hhea), [maxp](https://learn.microsoft.com/en-us/typography/opentype/spec/maxp), [post](https://learn.microsoft.com/en-us/typography/opentype/spec/post), [gasp](https://learn.microsoft.com/en-us/typography/opentype/spec/gasp), [STAT](https://learn.microsoft.com/en-us/typography/opentype/spec/stat).
