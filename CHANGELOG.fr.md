# Journal des changements

[English](CHANGELOG.md) | Français

## À paraître

## 0.1.0 — 14 octobre 2026

- Introduire des objets OpenType indépendants et immuables pour PHP 8.3+ sur une plateforme 64 bits, sans dépendance de production.
- Lire les répertoires SFNT individuels, conserver les tables et vérifier offsets bornés, nombres d'enregistrements, glyphes et sommes de contrôle.
- Lire les formats Unicode cmap 4, 12 et 14 et les métriques horizontales.
- Modéliser scripts, systèmes de langue, fonctionnalités et lookups GSUB ; appliquer substitutions simples, multiples, alternatives, ligatures et extensions avec une sélection explicite et les clusters d'origine.
- Rejeter les mécanismes sélectionnés non pris en charge. Les substitutions contextuelles, flags nécessitant GDEF, GPOS, variations et contours restent à implémenter.
- Conserver le document Adobe OTGuide.pdf fourni sans modification ; documenter les sources et la couverture en anglais et français.
- Comparer indépendamment les résultats pris en charge avec fontTools et HarfBuzz. La CI couvre PHP 8.3, 8.4 et 8.5.
