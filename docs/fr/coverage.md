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
| Exécution | Fonctionnalités explicites et obligatoires, ordre des lookups, variantes, clusters et sortie bornée ; flags de lookup à zéro |

Les tests couvrent la construction directe et les entrées binaires. Des contrôles
indépendants utilisent fontTools pour les données et HarfBuzz pour les
substitutions prises en charge sur des fontes reproductibles. Ils valident le
périmètre testé, pas toutes les tables OpenType.

## Travail restant

- Types GSUB contextuels, contextuels chaînés et inverses chaînés 5/6/8.
- Classes GDEF, filtrage/attachement des marques et flags correspondants.
- Positionnement GPOS, dont crénage et attachement des marques/formes cursives.
- Axes, interpolation des fontes variables et variations des fonctionnalités.
- Contours, exécution des instructions TrueType et charstrings CFF/CFF2.
- Sémantique des noms, OS/2, tables verticales, lignes de base, justification, mathématiques, couleur et bitmap.
- Collections, WOFF/WOFF2, sérialisation, sous-ensembles et shaping Unicode complet.

Les tables non interprétées restent accessibles comme octets. Une transformation
sélectionnée non prise en charge provoque une erreur explicite. La couverture
avance par versions cohérentes avec leurs tests et contrôles indépendants.

`resources/coverage.json` décrit ce périmètre par table et mécanisme. Les lectures
partielles d'en-têtes et l'accès aux octets se distinguent des mécanismes implémentés.
