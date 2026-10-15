# Des octets de fonte aux objets explicites

[English](../architecture.md) | Français

## Frontière avec l'application

L'application charge une fonte avec ses propres règles de stockage. `SfntReader`
reçoit ces octets et un objet `ReadLimits`. Il n'y a ni registre global, ni état
documentaire partagé, ni dépendance au PDF. `BinaryView` représente des octets
bornés ; les offsets sont relatifs à la vue de leur table. Le répertoire SFNT
conserve des enregistrements typés et les données, y compris les tables dont la
sémantique n'est pas implémentée.

## Identités et mesures

`Tag` représente les identifiants de quatre octets, complétés par des espaces,
des tables, scripts, langues et fonctionnalités. `CodePoint` est un scalaire
Unicode ; `GlyphId` identifie un glyphe dans une fonte. Les deux identités
constituent des contrats distincts.

`UnicodeCmapReader` sélectionne une correspondance Unicode de base prise en
charge. Les formats 4/12 couvrent le répertoire ; le format 14 décrit les
séquences de variation. Un caractère de base absent correspond au glyphe zéro ;
une séquence de variation non prise en charge reste distinguable.

`HorizontalMetricsReader` expose les avances et approches gauches en unités de
dessin, ainsi que les unités par em. L'appelant choisit la conversion des
coordonnées. Le lecteur n'applique ni crénage ni instance de fonte variable.

## Objets de layout

GSUB contient `ScriptList`, `FeatureList` et `LookupList`. Un script sélectionne
un système de langue, qui référence des fonctionnalités obligatoires ou
facultatives. Elles référencent des lookups ; les enregistrements Coverage et de
substitution décrivent les transformations.

`GsubReader` construit ces objets. `FeatureSelection` exprime script, langue,
fonctionnalités, choix de repli et politique des variantes.
`SubstitutionEngine` résout la sélection et renvoie une nouvelle `GlyphSequence`,
en conservant les clusters d'origine. Les objets se construisent aussi
directement. Leurs constructeurs vérifient les invariants locaux ; les lecteurs
contrôlent en plus les limites binaires et références entre enregistrements.
L'exécution vérifie les mécanismes des lookups sélectionnés.


## Définitions, positions et instances variables

`GdefTable` contient classes de glyphes/marques, ensembles de marques, indices
de points d'attache, curseurs de ligature et données variables natives
optionnelles. `GposReader` construit valeurs, ancres, matrices de paires et
programmes d'attachement/contexte immuables. `PositionedGlyphSequence` associe
les identités aux `GlyphPosition` initiales explicites ; `PositioningEngine`
renvoie les objets ajustés. Les buffers mutables appartiennent à une exécution,
y compris son graphe d'attachement différé.

`NormalizedCoordinates`, `FeatureVariations`, `ItemVariationStore` et
`VariationInstance` évaluent l'instance de layout choisie par l'appelant.
L'échelle Device et l'accès aux points après hinting sont des capacités
explicites de `PositioningContext`. L'identité d'attachement des ligatures est
indépendante des numéros de cluster. Voir le [guide du positionnement](positioning.md)
pour un exemple complet et ses limites.

## Relation avec PDFToolkit

Le core PDF possède les dictionnaires de fontes PDF, correspondances CID et
opérateurs de texte. `pdf-fonts` convertit les programmes TrueType externes pris
en charge vers ces objets. `opentype` représente un format de fonte indépendant
et ses transformations. Le core n'a besoin d'aucune dépendance de production
vers ce package.

Relier les glyphes sélectionnés au contenu PDF nécessite un encodage, un
positionnement et des correspondances d'extraction Unicode explicites. Cette
version ne modifie pas le convertisseur existant et n'ajoute pas de shaping
implicite à sa sortie.

Sources techniques : [SFNT](https://learn.microsoft.com/en-us/typography/opentype/spec/otff),
[cmap](https://learn.microsoft.com/en-us/typography/opentype/spec/cmap),
[hmtx](https://learn.microsoft.com/en-us/typography/opentype/spec/hmtx),
[tables communes de layout](https://learn.microsoft.com/en-us/typography/opentype/spec/chapter2)
et [GSUB](https://learn.microsoft.com/en-us/typography/opentype/spec/gsub).
