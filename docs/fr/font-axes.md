# Axes utilisateur et normalisation

[English](../font-axes.md) | Français

`FvarReader` lit les axes et instances nommées de `fvar` 1.0. `AvarReader`
lit les correspondances ordonnées par segments de `avar` 1.0. Ce sont des objets
immuables indépendants ; l’application fournit les octets et les coordonnées
utilisateur explicites.

```php
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\AvarReader;
use PDFToolkit\OpenType\Variation\FvarReader;
use PDFToolkit\OpenType\Variation\UserCoordinates;

$fvar = (new FvarReader())->read($font->table(new Tag('fvar')));
$avar = $font->hasTable(new Tag('avar'))
    ? (new AvarReader())->read($font->table(new Tag('avar')), count($fvar->axes))
    : null;
$coordinates = $fvar->normalize(new UserCoordinates(['wdth' => 81.25, 'wght' => 650.0]), $avar);
```

Le résultat conserve **l’ordre des axes de la fonte**, indépendamment de l’ordre
des clés d’entrée. Les valeurs absentes prennent leur défaut ; celles hors plage
sont bornées. Les tags inconnus sont refusés. Une table sans axe produit une
instance normalisée vide. Les axes cachés conservent leur position et participent
à la normalisation. Ce drapeau est une recommandation d’interface, pas une
instruction de suppression de l’axe.

Les valeurs utilisateur passent en 16.16, sont normalisées autour du défaut
asymétrique, éventuellement remappées par `avar`, puis arrondies en 2.14. Un axe
dégénéré ne divise jamais par zéro. Les `NormalizedCoordinates` fonctionnent
directement avec `VariationInstance` et `FeatureVariations` natifs. Cette étape
normalise la sélection ; elle n’instancie ni contours ni deltas de métriques.

Les instances conservent leurs identifiants de sous-famille et PostScript
facultatif, ainsi que toutes les coordonnées utilisateur. `instanceCoordinates($index)`
sélectionne explicitement un enregistrement. La recherche du nom est distincte ;
l’instance par défaut existe même sans enregistrement nommé. Les doublons sont
préservés sans politique automatique d’énumération d’interface ou de nommage.

Les lecteurs bornent les offsets et nombres cumulés avant allocation et refusent
les axes dupliqués, plages invalides et instances hors plage. Les correspondances
doivent être ordonnées et non rétrogrades. Conformément à la spécification, une
correspondance dépourvue d’une ancre identité -1/0/+1 est désactivée pour cet axe.
Les versions inconnues, dont `avar` 2, sont explicitement non prises en charge ;
les drapeaux réservés sont préservés sans nouvelle sémantique. Les tailles
d’enregistrement de l’en-tête sont validées pour la version 1.0.

`tools/validate_axes.py` confronte 89 sélections à fontTools et HarfBuzz, y compris
les valeurs absentes et hors plage. Les normalisations HarfBuzz correspondent
exactement. fontTools calcule en flottants : seuls les cas hors grille tolèrent
une unité 2.14. 204 autres cas de layout utilisent ces coordonnées et comparent
exactement glyphes et positions entières en unités de dessin. Les positions
fractionnaires restent couvertes par les tests natifs sans arrondi caché de
l’oracle.

Sources techniques : [fvar](https://learn.microsoft.com/en-us/typography/opentype/spec/fvar),
[avar](https://learn.microsoft.com/en-us/typography/opentype/spec/avar) et
[normalisation](https://learn.microsoft.com/en-us/typography/opentype/spec/otvaroverview#coordinate-scales-and-normalization).
