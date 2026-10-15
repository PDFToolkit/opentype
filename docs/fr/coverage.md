# Couverture de l'implémentation OpenType 1.9.1

[English](../coverage.md) | Français

## Source technique et guide d'utilisation

La source technique est [OpenType 1.9.1](https://learn.microsoft.com/en-us/typography/opentype/spec/).
Sa version est distincte de celle du package. Le document Adobe
[OTGuide.pdf fourni](../../resources/reference-documents/OTGuide.pdf) est un guide
d'utilisation d'octobre 2008, de 13 pages physiques. Il illustre ligatures,
petites capitales et variantes sans définir leurs enregistrements binaires.

Le guide d'origine est conservé : **826 379 octets**, SHA-256
`39bc32fa334c76d488312ef92c16a5e147315d1c73e8b6ead77f410d297739f4`.
Sa provenance figure dans `resources/reference-documents/metadata.json`.
Le PDF est exclu des archives Composer.

## Mécanismes implémentés

| Domaine | Couverture |
| --- | --- |
| Valeurs binaires | Vues big-endian bornées ; tags, scalaires Unicode et glyphes typés |
| SFNT | Répertoires individuels TrueType/OTTO, enregistrements, octets conservés, plages et sommes de contrôle |
| cmap | Formats Unicode de base 4/12 et séquences de variation du format 14 |
| Métriques horizontales | Unités par em, avances et approches gauches |
| Structures GSUB | Listes de scripts/langues/fonctionnalités/lookups, Coverage 1/2 et indices référencés |
| GSUB type 1 | Formats 1/2 de substitution simple |
| GSUB types 2/3/4 | Format 1 des substitutions multiples, alternatives et ligatures |
| GSUB type 7 | Format 1 d'extension autour des substitutions prises en charge |
| GSUB types 5/6/8 | Trois formats contextuels/chaînés ; substitutions inverses parcourues depuis la fin |
| GDEF 1.0/1.2/1.3 | Classes, filtrage/attachement des marques, points d'attache, trois formats de curseur et magasin de variations natif |
| GPOS types 1/2 | Formats simples 1/2, paires du format 1 et matrices de classes du format 2 |
| GPOS types 3/4/5/6 | Attaches cursives, marque-base, marque-ligature et marque-marque |
| GPOS types 7/8/9 | Trois formats contextuels/chaînés et extensions de positionnement |
| Variations du layout | Tables conditionnelles de fonctionnalités et interpolation ItemVariationStore sur des coordonnées normalisées explicites |
| Devices et points | Corrections signées sur 2/4/8 bits avec échelle explicite ; indices de points et fournisseur explicite de points après hinting |
| Exécution | Fonctionnalités explicites/obligatoires, ordre, variantes, flags GDEF, clusters, identités de composants de ligature et graphes bornés de lookups/sortie/attachement |

Les tests couvrent la construction directe et les entrées binaires. Des contrôles
indépendants utilisent fontTools pour les données et HarfBuzz pour les
substitutions, positions, attaches et variations sur des fontes originales
reproductibles. Les 17 cas initiaux, 49 cas avancés et 36 cas variables/Device
valident ce périmètre, pas toutes les tables OpenType. Les contrôles cursifs
comparent les origines et l'avance finale ; ceux des Devices tolèrent moins
d'une unité de dessin pour la quantification entière de HarfBuzz uniquement.

## Travail restant

- Normalisation des axes utilisateur, sémantique `fvar`/`avar`/STAT et instanciation complète des fontes variables, dont deltas de contours et métriques.
- Contours, exécution des instructions TrueType et charstrings CFF/CFF2.
- Sémantique des noms, OS/2, tables verticales, lignes de base, justification, mathématiques, couleur et bitmap.
- Collections, WOFF/WOFF2, sérialisation, sous-ensembles et shaping Unicode complet.

Les tables non interprétées restent accessibles comme octets. Une transformation
sélectionnée non prise en charge provoque une erreur explicite. La couverture
avance par versions cohérentes avec leurs tests et contrôles indépendants.

`resources/coverage.json` décrit ce périmètre par table et mécanisme. Les lectures
partielles d'en-têtes et l'accès aux octets se distinguent des mécanismes implémentés.
