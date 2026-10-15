# Journal des changements

[English](CHANGELOG.md) | Français

## À paraître

## 0.2.0 — 15 octobre 2026

- Exécuter les types GSUB 5/6 dans tous leurs formats contextuels et le type inverse 8, avec ordre des lookups imbriqués, récursion bornée et indices d'entrée après expansion.
- Lire GDEF 1.0/1.2/1.3 : classes de glyphes/marques, filtrage/attachement des marques, points d'attache, curseurs de ligature et données variables natives.
- Exécuter nativement les types GPOS 1 à 9 : ajustements simples/paires/classes, attaches cursives et marques, formats contextuels et extensions, depuis des positions initiales explicites.
- Conserver l'identité des composants de ligature indépendamment des clusters et les attaches pendant les modifications ultérieures de placement/avance.
- Évaluer les formats Device signés 1/2/3, l'interpolation ItemVariationStore native et les variations conditionnelles de fonctionnalités GSUB/GPOS sur des coordonnées normalisées explicites.
- Garder l'accès aux points après hinting explicite ; aucun bytecode TrueType/CFF, instanciation de fonte variable complète, shaping de script Unicode ou ordre bidirectionnel n'est implicite.
- Ajouter des fontes avancées/variables originales reproductibles, tests de troncature/entrées malformées/budgets, 102 cas HarfBuzz indépendants, guides bilingues et exemples exécutables.

## 0.1.0 — 14 octobre 2026

- Introduire des objets OpenType indépendants et immuables pour PHP 8.3+ sur une plateforme 64 bits, sans dépendance de production.
- Lire les répertoires SFNT individuels, conserver les tables et vérifier offsets bornés, nombres d'enregistrements, glyphes et sommes de contrôle.
- Lire les formats Unicode cmap 4, 12 et 14 et les métriques horizontales.
- Modéliser scripts, systèmes de langue, fonctionnalités et lookups GSUB ; appliquer substitutions simples, multiples, alternatives, ligatures et extensions avec une sélection explicite et les clusters d'origine.
- Rejeter les mécanismes sélectionnés non pris en charge. Les substitutions contextuelles, flags nécessitant GDEF, GPOS, variations et contours restent à implémenter.
- Conserver le document Adobe OTGuide.pdf fourni sans modification ; documenter les sources et la couverture en anglais et français.
- Comparer indépendamment les résultats pris en charge avec fontTools et HarfBuzz. La CI couvre PHP 8.3, 8.4 et 8.5.
