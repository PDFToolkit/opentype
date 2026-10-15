# Métriques de périphérique précalculées

[English](../device-metrics.md) | Français

Les objets `DeviceMetrics` lisent les déclarations `hdmx`, `LTSH` et `VDMX` du
producteur de la police. Ils retournent des métriques en pixels ou des seuils de
mise à l'échelle linéaire sans exécuter les instructions TrueType ni appeler un
moteur de rastérisation.

```php
use PDFToolkit\OpenType\DeviceMetrics\HorizontalDeviceMetricsReader;
use PDFToolkit\OpenType\DeviceMetrics\LinearThresholdReader;
use PDFToolkit\OpenType\DeviceMetrics\VerticalDeviceMetricsReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;

$widths = (new HorizontalDeviceMetricsReader())->read(
    $font->table(new Tag('hdmx')), $font->glyphCount(),
);
$pixelWidth = $widths->atPpem(12)?->width(new GlyphId(2));
$thresholds = (new LinearThresholdReader())->read(
    $font->table(new Tag('LTSH')), $font->glyphCount(),
);
$declaredLinear = $thresholds->declaresLinear(new GlyphId(2), 50);
$heights = (new VerticalDeviceMetricsReader())->read($font->table(new Tag('VDMX')));
$height = $heights->select(96, 72, 1)?->atPpem(12);
```

`hdmx` version 0 conserve chaque enregistrement ppem ordonné et toutes les largeurs
non signées en pixels. L'appelant fournit le ppem voulu ; une entrée absente donne
`null`, sans interpolation ni largeur supposée en unités de conception. L'alignement,
le bourrage nul, la largeur maximale, le nombre de glyphes et les limites cumulées
d'allocation sont vérifiés.

`LTSH` version 0 conserve un seuil par glyphe. `declaresLinear()` compare un ppem
vertical explicite à cette déclaration. Il ne vérifie pas l'affirmation du
producteur en exécutant la police. L'utilisation de ces tables et du drapeau
`head` associé reste un choix explicite de l'application.

`VDMX` versions 0/1 conserve les rapports d'aspect ordonnés et leurs groupes
partagés. La sélection compare des résolutions entières par produits croisés et
choisit le premier jeu de caractères et rapport correspondant, y compris un
rapport par défaut final s'il existe. Le jeu de caractères 1 désigne le sous-ensemble
Windows ANSI en version 0 ; la version 1 emploie le sens révisé de police entière.
L'appelant fournit ce sélecteur. Un ppem ou rapport absent donne `null` ; les
hauteurs ne sont jamais interpolées. Les bornes en pixels, l'ordre, les offsets
relatifs, le nombre de groupes distincts et les budgets cumulés sont validés avant
de suivre ou allouer les enregistrements.

La fixture reproductible `ToolkitDeviceMetrics.ttf` contient des déclarations
originales, sans prétendre à des mesures de rastérisation. L'oracle indépendant
fontTools vérifie chaque champ et les sélections dans les deux versions VDMX.
Les tests natifs couvrent aussi les troncatures, le bourrage, les compteurs, les
tailles dupliquées, les bornes inversées et les budgets.

Sources : [hdmx](https://learn.microsoft.com/en-us/typography/opentype/spec/hdmx),
[LTSH](https://learn.microsoft.com/en-us/typography/opentype/spec/ltsh),
[VDMX](https://learn.microsoft.com/en-us/typography/opentype/spec/vdmx).
