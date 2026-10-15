# Noms et métadonnées OS/2

[English](../font-metadata.md) | Français

`NameReader` modélise les deux versions de la table de noms. Chaque enregistrement
conserve ses identifiants de plateforme, encodage, langue et nom, et ses octets
originaux. Les tags linguistiques de version 1 sont décodés en UTF-16BE. Un index
de langue absent rend l’enregistrement inéligible à la sélection, sans langue
inventée en remplacement.

```php
use PDFToolkit\OpenType\Names\NameReader;
use PDFToolkit\OpenType\Names\UnicodeNameDecoder;
use PDFToolkit\OpenType\Metrics\Os2Reader;
use PDFToolkit\OpenType\Value\Tag;

$names = (new NameReader())->read($font->table(new Tag('name')));
$records = $names->select(6, 3, 1, 0x0409);
$postScriptName = $records === [] ? null : $records[0]->text(new UnicodeNameDecoder());
$metrics = (new Os2Reader())->read($font->table(new Tag('OS/2')));
$permission = $metrics->embeddingRights->permission();
```

La sélection est exacte et explicite. `UnicodeNameDecoder` traite UTF-16BE
Unicode/Windows Unicode et ISO ASCII/10646 ; les paires de substitution invalides
échouent sans caractères de remplacement. `MacintoshRomanNameDecoder` traite
Macintosh Roman. Les autres encodages historiques/personnalisés exigent un
`NameDecoder` applicatif. Préserver les enregistrements n’implémente pas tous les
codecs, la correspondance au registre BCP 47, les heuristiques de nommage des
sélecteurs de fontes ou le repli linguistique.

`Os2Table` expose tous les champs des versions 0–5 en valeurs immuables typées :
PANOSE, bits de plages Unicode/pages de codes, classification, métriques
indices/exposants et barrement, métriques typographiques/Windows, champs
hauteur/défaut/contexte versionnés et plages optiques. Les champs absents valent
`null` ; la version 0 historique réduite à 68 octets est reconnue. Les octets
supplémentaires sont ignorés selon la version déclarée. Ces métadonnées déclarées
ne prouvent pas la couverture des caractères, n’exécutent pas de substitution de
fonte et ne donnent pas les avances réelles des glyphes. Les deltas MVAR variables
sont un mécanisme distinct.

`EmbeddingRights` applique fsType selon la version : les versions 0–1 ignorent
les futurs drapeaux sans-subsetting/bitmap-seul ; les versions 0–2 interprètent
les usages combinés selon la règle autorisée la moins restrictive ; les versions
3–5 refusent les usages mutuellement exclusifs combinés. Lire ces déclarations
n’incorpore pas la fonte et n’accorde pas de licence indépendante.
`useTypographicMetrics()` expose le drapeau de sélection des versions 4 et
suivantes. Les plages optiques sont en vingtièmes de point, borne basse incluse,
haute exclue, 65535 représentant l’infini.

Les lecteurs contrôlent limites de stockage, tri des identifiants, longueurs
versionnées et budgets cumulés de chaînes/enregistrements avant copie. Les
références répétées consomment le budget cumulé même si elles partagent les mêmes
octets. L’oracle fontTools compare chaque nom et champ OS/2 des fixtures
originales et des six versions de table. Les tests natifs couvrent aussi name version 1,
Unicode supplémentaire, OS/2 historique et données invalides.

Sources techniques : [name](https://learn.microsoft.com/en-us/typography/opentype/spec/name)
et [OS/2](https://learn.microsoft.com/en-us/typography/opentype/spec/os2).
