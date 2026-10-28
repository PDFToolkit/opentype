# Collections, correspondances de caractères et paramètres de fonctionnalités

[English](../collections-and-feature-parameters.md) | Français

## Sélection explicite d'une collection

```php
use PDFToolkit\OpenType\Sfnt\CollectionReader;
use PDFToolkit\OpenType\Sfnt\SfntSerializer;

$collection = (new CollectionReader())->read($collectionBytes);
$font = $collection->font(1);
$standaloneBytes = (new SfntSerializer())->write($font);
```

Les versions TTC/OTC 1.0 et 2.0 peuvent contenir des faces TrueType et CFF. Les
offsets des répertoires et tables partagées se rapportent aux octets de collection.
Le lecteur contrôle chaque face, bornes, sommes de contrôle et budget cumulé des
répertoires ; les plages partagées identiques sont permises, les recouvrements
partiels et collisions répertoire/table sont refusés. Un indice absent échoue ;
aucune face n'est choisie implicitement. Les octets DSIG de version 2 sont bornés
et exposés comme données opaques, sans analyse de signature ni vérification de confiance.

`SfntSerializer` écrit une face sélectionnée entière, reconstruit répertoire trié,
padding et sommes de contrôle. Il conserve tables/glyphes sauf
`head.checkSumAdjustment` et la DSIG invalidée, supprimée. Il ne crée pas de
sous-ensemble, ne modifie pas les tables variables, ne fige pas une instance
variable et n'écrit pas de collection. Resérialiser ne prouve pas l'implémentation
de la sémantique de chaque table conservée.

## Famille complète des formats cmap Unicode

`UnicodeCmapReader` supporte les formats de base 0/2/4/6/8/10/12/13 et les séquences
de variation du format 14. Le format 2 utilise sous-en-têtes bornés, tableaux
relatifs et deltas modulo sur les glyphes non nuls. Le format 8 vérifie bitmap de
largeur mixte et groupes. Les formats 12/13 conservent des groupes compacts sans
allouer une carte par scalaire. Le format 13 est une correspondance de dernier
recours à glyphe constant et exige plateforme 0/encodage 6.

La sélection reste Unicode : Windows 3/1 exige le format 4, Windows 3/10 le 12 ;
le lecteur préfère une base Unicode supportée à répertoire complet et ne fusionne
pas les sous-tables. Il ne réinterprète pas les encodages historiques Macintosh/
Windows comme Unicode. Les enregistrements sont triés par plateforme, encodage
**et langue** ; plusieurs langues Macintosh distinctes peuvent coexister avec la
carte Unicode sélectionnée. Le reste partiel de cmap concerne la politique de
transcodage des encodages historiques.

## Paramètres sémantiques des fonctionnalités

`Feature::$parameters` expose `OpticalSizeParameters`, `StylisticSetParameters`
ou `CharacterVariantParameters` pour `size`, `ss01`–`ss20` et `cv01`–`cv99`.
L'ancien `$featureParams` brut reste disponible. Les ID de noms restent explicites ;
les résoudre via `NameTable` avec une langue sélectionnée par l'application.

```php
use PDFToolkit\OpenType\Layout\OpticalSizeFamily;
use PDFToolkit\OpenType\Layout\OpticalSizeParameters;

$caption = new OpticalSizeParameters(80, 1, 256, 60, 100);
$text = new OpticalSizeParameters(120, 1, 256, 100, 180);
$family = new OpticalSizeFamily('Example Regular', [$caption, $text]);
$selected = $family->select(12.0); // $text
```

Les cinq champs `size` sont en décipoints, pas en pixels ou coordonnées variables
`opsz`. La borne basse est exclusive, la haute inclusive. L'ID de sous-famille
zéro signifie que les autres champs de groupement sont ignorés, tout en gardant
la taille de dessin utile. Une famille reçoit des membres explicites ayant le
même ID non nul et des plages sans recouvrement ; elle choisit la plage contenant
la taille, sinon la plus proche, en préférant la plage haute en cas d'égalité.
L'appelant identifie la fonte associée à chaque objet retourné ; aucune recherche
système ni mise à l'échelle optique synthétique n'est effectuée.

Les paramètres stylistiques exposent l'ID du nom d'interface. Les variantes de
caractères exposent IDs du libellé/infobulle/échantillon, choix nommés et scalaires
Unicode, et résolvent une valeur de paramètre à partir de un vers son ID de nom.
Lire ces libellés n'active aucune fonctionnalité, ne choisit pas de variante, ne
dessine pas de palette et ne convertit pas la casse Unicode. `size` est une
fonctionnalité de métadonnées GPOS sans lookup, pas un positionnement de glyphes.

## Plages de fonctionnalités sans découper le contexte

```php
use PDFToolkit\OpenType\Substitution\FeatureRange;
use PDFToolkit\OpenType\Substitution\FeatureSelection;
use PDFToolkit\OpenType\Value\Tag;

$selection = new FeatureSelection(new Tag('latn'), ranges: [
    new FeatureRange(new Tag('liga'), 3, 8),
    new FeatureRange(new Tag('liga'), 5, 6, enabled: false),
]);
```

Les plages utilisent les valeurs des clusters source d'origine : début inclus,
fin exclue. Les fonctionnalités dans `features` sont actives globalement ; une
plage peut activer une fonctionnalité sinon absente ou désactiver une partie d'une
fonctionnalité globale. Les plages ultérieures du même tag prévalent. Une feature
requise de LangSys reste obligatoire. Les lookups partagés s'exécutent une seule
fois dans l'ordre du répertoire, là où l'un de leurs masques est actif. Le masque
est hérité lors des substitutions multiples et du premier glyphe consommé d'une
ligature ; `featureClusters` conserve cette identité séparément des clusters
fusionnés pour l'extraction. Si les clusters sont des positions de scalaires
Unicode, les plages portent sur ces positions, pas sur les octets UTF-8.

Les deux moteurs conservent le buffer complet : le contexte avant/après reste
visible hors sélection. Tous les glyphes d'entrée d'un contexte/ligature, les deux
glyphes d'une paire/attache cursive et les deux marque/cible d'une attache de marque
doivent être sélectionnés pour ce lookup. Les marques ignorées par GDEF restent
ignorées. L'inspection des plages consomme le budget d'opérations.
`validate_feature_ranges.py` compare ces règles à HarfBuzz, dont chevauchements,
expansions, tous les formats chaînés, substitution inverse et plages autour des
ligatures/positions. Un masque booléen choisit l'activation ; les valeurs de
variantes utilisent toujours la politique `AlternateSelector` explicite.
Itemisation et bidi restent distincts.

## Couverture du guide et frontières

Le document Adobe inchangé [OTGuide.pdf](../../resources/reference-documents/OTGuide.pdf)
contient les pages physiques 1–13 :

| Pages | Usage | Mécanisme implémenté ou frontière applicative |
| --- | --- | --- |
| 1 | Sommaire | Navigation documentaire |
| 2 | TrueType/CFF, otf/ttf/ttc, caractères/glyphes | Fontes individuelles, TTC/OTC, cmap et les deux modèles de contours |
| 3 | Faces optiques caption/text/subhead/display | Paramètres `size` et sélection explicite de famille ; installation/gestion OS externes |
| 4 | Installation | Application/système, aucune écriture cachée |
| 5–6 | Saisie Unicode, symboles, claviers, glyphes non encodés | Cartes Unicode et GID explicites ; politique clavier/import/transcodage externe |
| 7 | Substitutions un/plusieurs et positionnement | GSUB 1–8, GPOS 1–9 et clusters sources ; ordre des étapes du script explicite |
| 8 | Activation sur le texte sélectionné | Sélection explicite par segment/étape ; masques par clusters sources conservant le contexte complet |
| 9–10 | Capitales, ligatures, fractions, swashes, variantes | Lookups GSUB/GPOS de la fonte et choix de variante ; aucun dessin synthétique ni détection automatique de casse/fraction |
| 11 | Ensembles cumulatifs, chiffres, marques, formes contextuelles | Sélection multiple et exécution des marques/contextes ; noms ssXX ; jonction/itemisation Unicode et bidi externes |
| 12 | Palette glyphes/variantes et choix de fonte | GID, noms, enregistrements de variantes, paramètres ssXX/cvXX et STAT ; interface externe |
| 13 | Disponibilité dans les applications | Tags sélectionnables ; la feature `kern` n'est pas la table historique homonyme |

C'est un guide d'utilisation, pas une revendication de toutes les écritures,
interfaces ou tables OpenType. La [couverture](coverage.md) conserve l'inventaire
réel des tables restantes. `avar` version 2 reste une proposition d'extension hors
de la spécification 1.9.1 publiée employée ici. Conserver des octets ne vaut jamais
revendication d'implémentation.

## Contrôles indépendants

`tools/validate_cmaps.py` compare six formats supplémentaires à l'API publique
FreeType des cartes de caractères. `validate_collections.py` confronte chaque
face/table à fontTools TTCollection et rouvre les faces sérialisées avec sommes
de contrôle strictes. `validate_feature_parameters.py` compare tous les champs
modélisés à fontTools. `build_collection_fixtures.py --check` reconstruit les deux
collections originales MIT. Ces contrôles figurent dans la matrice PHP 8.3/8.4/8.5 ;
FreeType est une dépendance système de test uniquement. Ils ne vérifient ni la
confiance DSIG ni un rastériseur de fonte.

Sources techniques : [collections SFNT](https://learn.microsoft.com/en-us/typography/opentype/spec/otff),
[cmap](https://learn.microsoft.com/en-us/typography/opentype/spec/cmap),
[size et ensembles stylistiques](https://learn.microsoft.com/en-us/typography/opentype/spec/features_pt),
[variantes de caractères](https://learn.microsoft.com/en-us/typography/opentype/spec/features_ae).
