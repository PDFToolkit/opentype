# Contours CFF et CFF2

[English](../cff-outlines.md) | Français

`CffReader` consomme un `SfntFont` fourni par l’application, de signature `OTTO`. Il lit les structures INDEX et DICT CFF1 ou CFF2, les CharStrings, les sous-programmes globaux/locaux, la sélection FontDICT (formats 0/3 et format 4 CFF2) et le magasin de variations CFF2. Les compteurs, offsets, données de dictionnaires cumulées et tailles de programmes sont bornés. Les axes du magasin CFF2 doivent correspondre à `fvar` et sa FontMatrix à `unitsPerEm`.

```php
use PDFToolkit\OpenType\Cff\CffOutlineEngine;
use PDFToolkit\OpenType\Cff\CffReader;
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Variation\NormalizedCoordinates;

$cff = (new CffReader())->read($font);
$glyph = new GlyphId(1);
$outline = (new CffOutlineEngine())->outline(
    $cff,
    $glyph,
    new NormalizedCoordinates([0.5, -0.5]), // ordre original de fvar
);
$designPath = $cff->matrixFor($glyph)->transform($outline->path, $cff->unitsPerEm);
```

Les coordonnées sont nécessaires quand un programme CFF2 utilise `vsindex` ou `blend` ; le moteur ne choisit jamais implicitement une instance de variation. Le résultat contient des tracés cubiques fermés et absolus en **coordonnées CharString**. La conversion FontMatrix est explicite. Les matrices FontDICT des CFF1 CID se concatènent avec celle du TopDICT. Les largeurs CharString CFF1 restent distinctes de `hmtx` ; CFF2 ne renvoie aucune largeur CharString.

Le moteur exécute tous les opérateurs Type 2 de lignes/courbes/flex, les appels biaisés des sous-programmes locaux/globaux, l’arithmétique et la mémoire transitoire CFF1, ainsi que `vsindex`/`blend` CFF2. Chaque exécution de glyphe possède son propre état. `random` CFF1 exige un `CffRandomSource` fourni par l’application ; aucun générateur aléatoire n’est caché. Les budgets d’instructions, de points, de pile, de profondeur des sous-programmes et des composites sont explicites, et les cycles sont refusés. Les composites historiques `endchar` à noms résolvent leur base/accent via **StandardEncoding**, indépendamment de l’encodage propre de la police.

Les charsets CFF1 couvrent les formats 0/1/2 et les trois charsets prédéfinis. Le charset immuable expose les identités SID/CID et les octets originaux des chaînes personnalisées. Les encodages Standard/Expert et personnalisés 0/1 avec suppléments fournissent des correspondances explicites de codes historiques vers les glyphes ; la sélection Unicode continue de passer par `cmap`.

`CffPrivateDictionaryInstance` évalue les blends PrivateDICT CFF2 pour des coordonnées normalisées explicites et renvoie un nouveau dictionnaire. Les opérandes tableaux conservent leur représentation différentielle spécifiée : interpréter les tableaux BlueValues ou StemSnap reste une opération distincte de métadonnées de hinting. Les entrées DICT non interprétées conservent leur séquence originale d’opérateurs/opérandes ; cela ne signifie pas que chaque métadonnée PostScript possède un objet sémantique de haut niveau.

Les instructions de hampes et de masques de hinting sont consommées avec vérification des longueurs. Flex produit deux courbes cubiques. Il s’agit de **géométrie sans hinting**, pas d’une rastérisation CFF : ajustement des hampes, alignement des zones bleues, contrôle des contreformes et choix de flex selon les pixels ne sont pas appliqués. Les polices synthétiques et opérateurs multiple-master historiques sortent de cette implémentation OpenType Type 2. Les instructions TrueType et `gvar` relèvent de mécanismes séparés.

`tools/validate_cff.py` compare les fixtures CFF1/CFF2 originales et reproductibles ainsi que la fixture Unicode CFF à fontTools et HarfBuzz : chaque opérateur de tracé, quatre flex, appels locaux/globaux, masques, encodage personnalisé, composites historiques et blends à deux axes. Les tests natifs couvrent programmes et INDEX malformés, budgets, cycles, nombres, blends privés et composition des matrices.

Sources techniques : [OpenType CFF](https://learn.microsoft.com/en-us/typography/opentype/spec/cff), [OpenType CFF2](https://learn.microsoft.com/en-us/typography/opentype/spec/cff2), [spécification Adobe CFF 5176](https://partners.adobe.com/public/developer/en/font/5176.CFF.pdf), [spécification Adobe Type 2 5177](https://partners.adobe.com/public/developer/en/font/5177.Type2.pdf).
