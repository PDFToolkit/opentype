# Métriques verticales et variables

[English](../variable-metrics.md) | Français

`VerticalMetricsReader` lit tous les champs `vhea` 1.0/1.1 et les avances/marges
hautes `vmtx`, y compris la répétition de la dernière avance complète.
`VerticalOriginsReader` lit le défaut et les exceptions `VORG` des fontes CFF/CFF2.
Il refuse cet usage sur TrueType, où les origines exigent `glyf` et `vmtx`. Ces
lecteurs donnent les métriques par défaut sans activer implicitement variation
ou hinting.

```php
use PDFToolkit\OpenType\Value\GlyphId;
use PDFToolkit\OpenType\Value\Tag;
use PDFToolkit\OpenType\Variation\GlyphMetricAxis;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationsReader;
use PDFToolkit\OpenType\Variation\GlyphMetricVariationInstance;
use PDFToolkit\OpenType\Variation\MvarReader;
use PDFToolkit\OpenType\Variation\MvarInstance;

$table = (new GlyphMetricVariationsReader())->read(
    $font->table(new Tag('HVAR')), GlyphMetricAxis::Horizontal,
    $font->glyphCount(), count($fvar->axes),
);
$instance = new GlyphMetricVariationInstance($table, $coordinates);
$advance = $horizontalMetrics->advanceWidth(new GlyphId(2))
    + $instance->advanceDelta(new GlyphId(2));
$mvar = new MvarInstance(
    (new MvarReader())->read($font->table(new Tag('MVAR')), count($fvar->axes)),
    $coordinates,
);
$ascender = $mvar->adjust(new Tag('hasc'), $os2->sTypoAscender);
```

`GlyphMetricVariationsReader` traite HVAR/VVAR 1.0. Il contrôle les nombres d’axes
et glyphes explicites, les correspondances compactées de deltas et toutes les
lignes référencées. Sans table de correspondance d’avances, les indices de glyphes
sont implicites. Une table courte répète son dernier élément conformément à la
spécification. La sentinelle sans variation donne zéro. Le lecteur partagé
`DeltaSetIndexMapReader` traite les deux formats de compte ; HVAR/VVAR exigent le 0.

L’instance `GlyphMetricVariationInstance` sélectionnée retourne les deltas
flottants en unités de dessin issus de l’interpolation native. Les marges
gauche/haute et droite/basse sont `firstBearingDelta` et `secondBearingDelta` ;
les correspondances facultatives absentes retournent `null`, pas zéro.
`verticalOriginDelta` conserve aussi cette absence. Les variations des points
fantômes TrueType peuvent être nécessaires si une correspondance manque. Le
consommateur ne doit ni traiter cette absence comme une métrique constante, ni
réappliquer ces deltas si un moteur de contours a déjà modifié les mêmes métriques.

`MvarReader` lit les enregistrements triés par tag et le stockage natif associé ;
`MvarInstance` les évalue et ajuste les défauts fournis explicitement. Un tag
absent donne zéro. Les tags privés sont préservés et seulement utilisés sur
requête explicite. Aucun objet métrique n’est modifié, aucun calcul de contour
n’est déduit et aucune politique d’arrondi, de bornage des avances ou de sélection
des métriques de ligne n’est implicite.

Une fonte reproductible à deux axes exerce avances/marges, correspondances
absentes et valeurs globales. Sur 30 instances, 2 520 métriques de glyphes et
90 valeurs globales sont comparées à fontTools. Les avances horizontales et
verticales correspondent aussi à HarfBuzz. Les origines CFF VORG sont vérifiées
indépendamment par HarfBuzz ; formats compactés, tailles, correspondances
implicites, sentinelles, indices invalides et budgets ont des tests natifs.

Sources : [vhea](https://learn.microsoft.com/en-us/typography/opentype/spec/vhea),
[vmtx](https://learn.microsoft.com/en-us/typography/opentype/spec/vmtx),
[VORG](https://learn.microsoft.com/en-us/typography/opentype/spec/vorg),
[HVAR](https://learn.microsoft.com/en-us/typography/opentype/spec/hvar),
[VVAR](https://learn.microsoft.com/en-us/typography/opentype/spec/vvar),
[MVAR](https://learn.microsoft.com/en-us/typography/opentype/spec/mvar) et
[formats de variation](https://learn.microsoft.com/en-us/typography/opentype/spec/otvarcommonformats).
