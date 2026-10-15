# OpenType

[English](README.md) | Français

**Métadonnées, contours variables, métriques et layout OpenType en pur objet pour PHP 8.3+.**

OpenType fait partie de [PDFToolkit](https://github.com/PDFToolkit) et reste
indépendant du PDF : installer `pdftoolkit/opentype` n'installe aucun package PDF.
Ses noms et structures suivent la [spécification OpenType 1.9.1](https://learn.microsoft.com/en-us/typography/opentype/spec/).

Lire les fontes TrueType/OTTO individuelles, leurs métadonnées multilingues et
métriques horizontales/verticales ; normaliser les axes utilisateur, évaluer les
métriques variables, assembler les contours TrueType quadratiques et exécuter les
programmes cubiques CFF/CFF2. Les substitutions GSUB et le positionnement GPOS
utilisent des objets immuables de script, langue, fonctionnalité, lookup, ancre et
variation. L'application choisit explicitement instances et politiques d'exécution.
Il n'y a ni registre global ni accès fichier caché.

```sh
composer require 'pdftoolkit/opentype:^0.3'
```

PHP 8.3 ou ultérieur et une plateforme 64 bits sont requis. Aucune dépendance de
package ni extension n'est requise en production. La CI vérifie PHP 8.3, 8.4 et 8.5.

## Suivre les objets

```php
use PDFToolkit\OpenType\Cmap\UnicodeCmapReader;
use PDFToolkit\OpenType\Sfnt\SfntReader;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Substitution\GlyphSequence;
use PDFToolkit\OpenType\Substitution\GsubReader;
use PDFToolkit\OpenType\Substitution\SubstitutionEngine;
use PDFToolkit\OpenType\Value\CodePoint;
use PDFToolkit\OpenType\Value\Tag;

// L'application fournit les octets ; la bibliothèque n'ouvre pas de fichier.
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

La table de correspondance transforme les scalaires Unicode en glyphes de cette
fonte. Le lecteur GSUB construit ses tables de layout. Le moteur sélectionne le
système de langue et les fonctionnalités, applique leurs lookups et renvoie une
nouvelle séquence. Une ligature `ffi` n'est produite que si la fonte et la
sélection la définissent. Les clusters d'origine restent disponibles pour le
traitement ultérieur du texte.

Les guides suivent les contrats des objets :

- [Substitutions](docs/fr/substitutions.md) et [positionnement](docs/fr/positioning.md).
- [Axes utilisateur](docs/fr/font-axes.md), [métriques variables](docs/fr/variable-metrics.md),
  [noms et OS/2](docs/fr/font-metadata.md), [en-têtes et styles](docs/fr/font-headers-and-styles.md).
- [Contours TrueType](docs/fr/truetype-outlines.md), [gvar/cvar et métriques fantômes](docs/fr/truetype-variations.md),
  [contours CFF/CFF2](docs/fr/cff-outlines.md), [métriques précalculées](docs/fr/device-metrics.md).

L'[architecture](docs/fr/architecture.md), la [couverture](docs/fr/coverage.md), la
[politique de sécurité](SECURITY.fr.md) et la [documentation partagée](https://pdftoolkit.github.io/fr/modules/opentype/)
décrivent les frontières. Les pages API proviennent du code exact publié.

## Périmètre de 0.3

| Mécanisme | Implémentation |
| --- | --- |
| SFNT individuel et cmap | Répertoires TrueType/OTTO, sommes de contrôle et correspondances Unicode 4/12/14 |
| Métadonnées | Enregistrements complets head/hhea/maxp/post, name 0/1, OS/2 0–5 et STAT 1.0–1.2 |
| Axes et métriques | Normalisation fvar/avar 1.0, métriques horizontales/verticales, HVAR/VVAR/MVAR natifs |
| Géométrie TrueType | Décodage loca/glyf borné, assemblage composite, tracés quadratiques, gvar/cvar natifs et métriques fantômes explicites |
| Géométrie CFF/CFF2 | Programmes Type 2 et sous-programmes bornés, tracés cubiques, matrices explicites, encodages et blends natifs |
| Layout | GSUB 1–8, GDEF, GPOS 1–9, tous les formats contextuels, variations natives et corrections Device |
| Déclarations de périphérique | Recommandations gasp, largeurs hdmx, seuils LTSH et hauteurs VDMX avec sélection explicite |

L'[inventaire de couverture](docs/fr/coverage.md) identifie 27 périmètres de tables
implémentés, 4 tables partielles et 19 en attente. Exécution des instructions
TrueType, application des hints CFF, tables de couleur/bitmap/lignes de
base/mathématiques/justification, tables auxiliaires historiques, collections,
WOFF/WOFF2, écriture et sous-ensembles restent absents. Exécuter une instance de
contour non hinté ou de métriques n'écrit pas une fonte statique. Les octets
conservés n'établissent pas de support sémantique.

Les moteurs de layout consomment une séquence de glyphes déjà ordonnée. Shaping
Unicode complet, ordre bidirectionnel et rastérisation demandent des consommateurs
distincts. Incorporation PDF et conversion HTML gardent leurs propres contrats de
formats explicites. L'API `0.x` peut évoluer entre versions mineures.

Le [document Adobe OTGuide.pdf fourni](resources/reference-documents/OTGuide.pdf)
est conservé sans modification comme référence d'utilisation. Son nom, ses
13 pages et son SHA-256 sont enregistrés à côté. Les définitions techniques
utilisent la spécification officielle. Le guide conserve ses droits d'origine ;
MIT porte sur le code du package. Le PDF et les fixtures de développement sont
exclus des distributions Composer.

## Développement

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

L'exemple utilise une fonte reproductible créée pour les tests et affiche les
substitutions. Les validateurs indépendants figurent dans `tools/`.
Le [journal des versions](CHANGELOG.fr.md) décrit chaque publication.
