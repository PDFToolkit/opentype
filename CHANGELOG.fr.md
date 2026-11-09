# Journal des changements

[English](CHANGELOG.md) | Français

## Unreleased

## 0.4.1 - 2026-11-09

- Mettre à jour la dépendance de développement verrouillée `symfony/filesystem` vers 7.4.23 pour corriger [GHSA-6prc-h956-hxjq](https://github.com/symfony/symfony/security/advisories/GHSA-6prc-h956-hxjq) dans l’outillage de style du code.
- Conserver le code de production, les API publiques, les exigences PHP et les versions et références de tous les autres packages verrouillés.

## 0.4.0 — 2026-10-28

- Ajouter sélection TTC/OTC 1/2 bornée et sérialisation entière d'une face SFNT, avec tables partagées et suppression de la DSIG invalidée.
- Implémenter les cmap Unicode 0/2/6/8/10/13 avec 4/12/14 et corriger l'ordre par langue des encodages ; comparer à FreeType.
- Exposer paramètres typés size/ssXX/cvXX, sélection de famille optique et métadonnées Name INDEX/ROS CFF, comparés à fontTools.
- Ajouter des plages de fonctionnalités sur clusters source dans GSUB/GPOS sans couper les contextes ; préserver leur identité à travers les substitutions et comparer à HarfBuzz.
- Actualiser carte du guide Adobe, couverture exacte et documentation complète FR/EN. Aucune VM de hinting, shaping Unicode complet, écriture d'instance statique ou confiance de signature n'est implicite.

## 0.3.0 — 15 octobre 2026

- Lire et sélectionner explicitement les métriques précalculées hdmx/LTSH/VDMX avec des objets immuables bornés et un oracle fontTools.
- Compléter les modèles head/hhea/maxp/post, les recommandations gasp versionnées et les valeurs d’axes STAT 1.0–1.2 avec correspondance explicite ; comparer chaque champ à fontTools.
- Évaluer les tuples gvar/cvar natifs, interpoler les deltas de contours omis et varier les composites imbriqués avec métriques fantômes explicites, USE_MY_METRICS et attachement fantôme ; conserver les fractions du CVT non mis à l’échelle.
- Exécuter les tracés cubiques Type 2 CFF/CFF2 bornés, sous-programmes, blends natifs explicites, charsets/encodages historiques et composites ; comparer la géométrie à fontTools/HarfBuzz.
- Décoder loca/glyf bornés et assembler les composants non hintés avec politique d’offset explicite, budgets de cycles/sortie et chemins quadratiques ; comparer points et géométrie avec fontTools/HarfBuzz.
- Lire métriques verticales et origines CFF ; évaluer les deltas HVAR/VVAR/MVAR natifs par instances explicites, correspondances d’indices bornées et comparaisons fontTools/HarfBuzz indépendantes.
- Modéliser name versions 0/1 avec enregistrements multilingues exacts et décodeurs explicites ; exposer tous les champs OS/2 versions 0–5, droits d’incorporation versionnés et plages optiques.
- Lire fvar/avar 1.0 par objets immuables d’axes et d’instances nommées, sélection utilisateur explicite, normalisation bornée et contrôles indépendants fontTools/HarfBuzz sur deux axes.

- Documenter les 50 périmètres de tables et les restes en anglais/français : 27 implémentés, 4 partiels, 19 en attente ; aucune VM, rastériseur, shaping complet, décodeur de conteneur ou écriture de fonte n’est implicite.

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
