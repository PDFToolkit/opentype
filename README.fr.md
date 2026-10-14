# OpenType

[English](README.md) | Français

**Des objets de fonte explicites et des substitutions de glyphes pour PHP 8.3+.**

OpenType fait partie de [PDFToolkit](https://github.com/PDFToolkit) et reste
indépendant du PDF : installer `pdftoolkit/opentype` n'installe aucun package PDF.
Ses noms et structures suivent la [spécification OpenType 1.9.1](https://learn.microsoft.com/en-us/typography/opentype/spec/).

Cette première version lit une fonte SFNT individuelle, ses correspondances
Unicode et ses métriques horizontales, puis applique les substitutions GSUB
prises en charge avec des objets immuables de script, langue, fonctionnalité et
lookup. L'application choisit explicitement les fonctionnalités et les variantes.

```sh
composer require 'pdftoolkit/opentype:^0.1'
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

Le [didacticiel des substitutions](docs/fr/substitutions.md) explique ligatures,
petites capitales et variantes. L'[architecture](docs/fr/architecture.md), la
[couverture](docs/fr/coverage.md), la [politique de sécurité](SECURITY.fr.md) et
la [documentation commune](https://pdftoolkit.github.io/fr/modules/opentype/)
précisent les contrats. Les pages d'API proviennent de la version publiée exacte.

## Couverture de la version 0.1

| Mécanisme | Implémentation |
| --- | --- |
| Répertoire SFNT individuel | Identités TrueType et OTTO, tables conservées, lectures bornées et politiques de somme de contrôle |
| Correspondances Unicode | Formats cmap 4/12 et sélecteurs de variation du format 14 |
| Métriques horizontales | Unités par em, avances et approches gauches |
| GSUB | Substitutions Single, Multiple, Alternate, Ligature et Extension ; formats Coverage 1/2 |
| Sélection | Script, langue, fonctionnalités obligatoires/demandées et variantes explicites ; flags de lookup à zéro |

Les substitutions contextuelles et inverses, les flags nécessitant GDEF, le
positionnement et le crénage GPOS, les fontes variables, l'interprétation des
contours et l'écriture de fontes restent à implémenter. L'accès aux octets ne
signifie pas que le mécanisme d'une table est pris en charge. Le moteur reçoit
des glyphes déjà ordonnés ; ce n'est pas un moteur complet de shaping Unicode ou
de texte bidirectionnel. L'API `0.x` peut évoluer entre versions mineures.

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
```

L'exemple utilise une fonte reproductible créée pour les tests et affiche les
substitutions. Les validateurs indépendants figurent dans `tools/`.
Le [journal des versions](CHANGELOG.fr.md) décrit chaque publication.
