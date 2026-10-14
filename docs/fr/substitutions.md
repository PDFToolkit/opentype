# Ligatures, petites capitales et variantes de glyphes

[English](../substitutions.md) | Français

## D'abord Unicode, ensuite les glyphes

Une lettre n'est pas un identifiant de glyphe. Le même scalaire Unicode peut
avoir des glyphes différents selon la fonte ; une ligature peut représenter
plusieurs caractères par un glyphe. Résoudre les objets `CodePoint` avec
`UnicodeCharacterMap`, puis construire la `GlyphSequence`.

L'[exemple du README](../../README.fr.md#suivre-les-objets) commence avec `f`, `f`,
`i`, obtient leurs glyphes via cmap et demande la fonctionnalité latine `liga`.
Si la fonte définit cette ligature pour la sélection, le résultat contient son
glyphe et conserve l'origine des clusters.

## Sélectionner la fonctionnalité

`FeatureSelection` décrit script, système de langue, fonctionnalités et choix de
repli. Le système de langue par défaut appartient à un script. Les tags de
langue OpenType sont des identifiants de fonte, pas des locales comme `fr-FR`.

Le tag exprime un rôle ; ses lookups définissent la transformation. `smcp`
sélectionne les petites capitales si la fonte fournit cette fonctionnalité.
Il ne réduit pas géométriquement les capitales.

```php
$smallCapitals = new FeatureSelection(new Tag('latn'), features: [new Tag('smcp')]);
$result = (new SubstitutionEngine())->apply($gsub, $smallCapitals, $input);
```

Le moteur inclut la fonctionnalité obligatoire du système de langue choisi.
Chaque lookup sélectionné est exécuté dans l'ordre de la liste, une seule fois
même si plusieurs fonctionnalités le référencent. Sa première sous-table
correspondante fournit la transformation.

## Choisir une variante

`AlternateIndex` exprime le choix de l'application avec un indice commençant à
un. Il n'y a ni préférence aléatoire ni sélection globale cachée.

```php
use PDFToolkit\OpenType\Substitution\AlternateIndex;

$alternates = new FeatureSelection(
    new Tag('latn'),
    features: [new Tag('salt')],
    alternateSelector: new AlternateIndex(1),
);
$result = (new SubstitutionEngine())->apply($gsub, $alternates, $input);
```

La fonctionnalité doit exister et utiliser des lookups pris en charge. Une
fonctionnalité absente ne crée pas de glyphes. Un lookup sélectionné non pris en
charge provoque une erreur explicite ; une substitution alternative sélectionnée
nécessite un sélecteur de variante.

## Examiner le résultat

Depuis un clone du dépôt :

```sh
composer install
php examples/substitution.php
php examples/manual-ligature.php
```

L'exemple démontre `liga`, `smcp`, `salt` et une substitution multiple avec la
fonte de test reproductible. La sortie contient les glyphes et clusters
d'origine ; le validateur indépendant compare les transformations prises en
charge avec HarfBuzz. Ce sont des résultats du format de fonte, sans rendu PDF.

L'[exemple de construction manuelle](https://github.com/PDFToolkit/opentype/blob/v0.1.0/examples/manual-ligature.php)
construit directement Coverage, LigatureSet, LookupList, FeatureList et ScriptList.
Ses identités illustratives sont `f=4`, `i=5`, `ffi=7` : l'entrée `[4,4,5]` devient
`[7]` avec le cluster d'origine `[0]`. Ce chemin utilise le même moteur sans lire
de fonte. L'[exemple de métriques](https://github.com/PDFToolkit/opentype/blob/v0.1.0/examples/font-metrics.php)
explique séparément les correspondances Unicode, unités de dessin et sélecteurs
de variation.

Positionnement, mise en lignes et encodage PDF demandent un traitement explicite
supplémentaire. GPOS et le shaping complet restent à implémenter ; sélectionner
les glyphes ne suffit pas à produire le texte final d'une page.

Voir la [couverture](coverage.md), [GSUB](https://learn.microsoft.com/en-us/typography/opentype/spec/gsub)
et le [registre des fonctionnalités](https://learn.microsoft.com/en-us/typography/opentype/spec/featurelist).
