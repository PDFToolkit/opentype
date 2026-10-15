# Couverture de l'implémentation OpenType 1.9.1

[English](../coverage.md) | Français

## Source technique et guide d'utilisation

La source technique est [OpenType 1.9.1](https://learn.microsoft.com/en-us/typography/opentype/spec/).
Sa version est distincte de celle du package. Le document Adobe
[OTGuide.pdf](../../resources/reference-documents/OTGuide.pdf) fourni est un guide
d'utilisation d'octobre 2008 de 13 pages physiques. Il illustre ligatures, petites
capitales et variantes, sans définir leurs enregistrements binaires.

L'original est conservé : **826 379 octets**, SHA-256
`39bc32fa334c76d488312ef92c16a5e147315d1c73e8b6ead77f410d297739f4`.
Sa provenance figure dans `resources/reference-documents/metadata.json`.
Le PDF est exclu des archives Composer.

## Mécanismes implémentés en 0.3

Le [manifeste de couverture](../../resources/coverage.json) inventorie 50 tables
OpenType : 27 implémentées dans le périmètre versionné ci-dessous, 4 partielles
et 19 en attente. Un modèle de table implémenté n'implique pas toutes les politiques
de consommation ni un moteur de rendu complet.

| Domaine | Périmètre pris en charge |
| --- | --- |
| Binaire et SFNT | Vues bornées, identités typées, répertoires TrueType/OTTO individuels, enregistrements et politiques de sommes de contrôle |
| cmap (partiel) | Formats Unicode 4/12 et séquences de variation au format 14 |
| head, hhea, maxp, post | Champs déclarés complets ; maxp 0.5/1.0 et post 1/2/2.5/3, dont noms de glyphes |
| name, OS/2 | Enregistrements et langues name 0/1 ; décodage Unicode/ASCII/Macintosh Roman explicite ; champs OS/2 0–5 et droits d'incorporation |
| hmtx, vhea, vmtx, VORG | Avances/approches horizontales et verticales, en-tête vertical versionné et origines CFF |
| fvar, avar, STAT | Axes/instances nommées ordonnés ; normalisation utilisateur et cartes avar 1.0 explicites ; quatre formats de valeurs STAT 1.0–1.2 |
| HVAR, VVAR, MVAR | Interpolation native régions/deltas, cartes d'indices bornées et ajustement explicite des métriques |
| loca, glyf (partiel) | Deux formats loca ; contours simples/composites ; assemblage non hinté, transformations affines, attaches entre points, tracés quadratiques et métriques fantômes explicites |
| gvar, cvar, cvt | Décodage natif des tuples, interpolation des contours clairsemés, deltas composites/fantômes et variation du CVT non mis à l'échelle |
| CFF, CFF2 (partiel) | INDEX/DICT/FDSelect bornés, tracés Type 2 et sous-programmes, matrices explicites, charsets/encodages/composites CFF et blends CFF2 |
| GSUB | Types 1–8, tous les formats contextuels/chaînés, sélection script/langue/fonctionnalités et variations natives |
| GDEF | Versions 1.0/1.2/1.3 : classes, filtrage, points d'attache, formats de curseur 1–3 et données variables natives |
| GPOS | Types 1–9, tous les formats contextuels/chaînés, ajustements paires/classes, attaches cursives/marques et variations natives |
| Corrections Device | Valeurs signées 2/4/8 bits avec échelle explicite ; indices de points hintés nécessitant un fournisseur explicite |
| gasp, hdmx, LTSH, VDMX | Recommandations de rendu versionnées, métriques précalculées en pixels, seuils et sélection explicite de taille/rapport d'aspect |
| Budgets d'exécution | Enregistrements/octets cumulés, points/glyphes de sortie, opérations, profondeur et cycles des lookups/composants/sous-programmes |

Les lecteurs retournent des objets immuables. L'exécution reçoit explicitement
fonctionnalités, variantes, coordonnées d'axes, échelles, fournisseurs de métriques
et positions initiales. Les buffers mutables se limitent à une lecture ou exécution.

## Tables restant à implémenter

Ces **19 tables restent non interprétées**, au-delà de leurs octets SFNT bornés :

| Groupe | Tables | Travail manquant et dépendances |
| --- | --- | --- |
| Programmes TrueType | `fpgm`, `prep` | Modèles d'instructions et machine virtuelle ; état graphique partagé, CVT/stockage, pile et appels bornés |
| Lignes de base, justification, mathématiques | `BASE`, `JSTF`, `MATH` | Modèles et consommateurs explicites ; réutilisation des coverages de layout, données Device/variation et lookups bornés |
| Couleurs en couches | `COLR`, `CPAL` | Palettes/versions, graphe de peinture, cycles bornés et variations ; peinture effective distincte |
| Glyphes SVG et bitmap | `SVG `, `EBDT`, `EBLC`, `EBSC`, `CBDT`, `CBLC`, `sbix` | Plages de glyphes, strikes, indices, métriques et charges utiles ; codecs/rendu restent explicites |
| Historiques et auxiliaires | `kern`, `MERG`, `meta`, `PCLT`, `DSIG` | Modèles versionnés et consommateurs ; lire un paquet DSIG ne vérifierait ni signature ni confiance |

Les quatre **tables partielles** ont les limites suivantes :

- `cmap` : formats 0/2/6/8/10/13 et politiques historiques de correspondance absents.
- `glyf` : géométrie, variation composite et métriques fantômes s'exécutent ; les
  octets d'instructions conservés ne sont pas exécutés. Ajustement à la grille et
  arrondi aux pixels nécessitent une machine virtuelle.
- `CFF ` : la géométrie Type 2 prise en charge s'exécute, avec arithmétique,
  sous-programmes et composites dépréciés. Application des hints et interprétation
  de toutes les métadonnées DICT de haut niveau absentes ; charstrings Type 1 et
  mécanismes historiques de fontes synthétiques/MM hors du contrat CFF supporté.
- `CFF2` : géométrie cubique, blends de charstrings et du DICT privé s'exécutent.
  Application des hints et rastérisation absentes ; conserver les opérandes DICT
  n'implémente pas tous les consommateurs de métadonnées.

`avar` version 2 n'est pas pris en charge. Correspondance locale/registre BCP 47,
nommage automatique des styles et génération des noms PostScript d'instances ne
sont pas fournis par `name`/STAT. Une instance ne réécrit jamais silencieusement
les métadonnées par défaut : l'appelant applique explicitement MVAR/HVAR/VVAR ou
les points fantômes gvar, sans double application.

## Conteneurs et responsabilités applicatives distinctes

Collections TTC/OTC, décodage WOFF/WOFF2, sérialisation, sous-ensembles et écriture
d'une instance statique de fonte variable restent en attente. Évaluer une instance
de contours ou métriques ne produit pas un fichier réécrit. Les conteneurs demandent
des offsets partagés bornés et une décompression explicite ; l'écriture nécessite
la reconstruction des tables dépendantes et sommes de contrôle, et les sous-ensembles
la fermeture des dépendances de glyphes/lookups/variations.

La bibliothèque ne fournit pas de rastériseur, de shaping Unicode complet,
d'ordre bidirectionnel, de coupure de lignes, de découverte des fontes système,
de repli linguistique/typographique, de décision de licence ni de politique de
confiance des signatures. Ces consommateurs peuvent utiliser les objets, mais
nécessitent leurs propres algorithmes et capacités explicites. La liste de formats
supportés par l'adaptateur PDF reste indépendante : cette version n'étend pas
implicitement l'incorporation PDF ni le shaping HTML.

Les mécanismes sélectionnés non supportés échouent explicitement. Les octets de
tables et d'instructions/charges utiles opaques ne comptent jamais comme des
algorithmes implémentés.

## Preuves de validation

Les tests natifs couvrent objets construits, fontes binaires valides, troncatures,
références invalides, offsets partagés, cycles et budgets cumulés. Les fixtures MIT
originales sont reproductibles octet par octet. fontTools vérifie indépendamment
chaque champ modélisé, contours, noms et deltas ; HarfBuzz compare les résultats
GSUB/GPOS sélectionnés, axes normalisés, métriques et tracés quadratiques/cubiques.
L'oracle des métriques précalculées vérifie les déclarations, pas la rastérisation.

Les oracles prennent explicitement en compte les conventions d'origine et la
quantification entière de HarfBuzz lorsqu'elles sont documentées. Ces tolérances
bornées ne remplacent pas les comparaisons strictes d'identités, enregistrements ou
valeurs exactement représentables. La qualification exécute tests natifs, analyse
statique, formatage, contrôles Composer, identité des références, reconstructions
de toutes les fixtures et oracles indépendants sur PHP 8.3/8.4/8.5.
