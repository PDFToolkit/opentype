<?php

declare(strict_types=1);

use PDFToolkit\OpenType\Binary\BinaryView;
use PDFToolkit\OpenType\Layout\CharacterVariantParameters;
use PDFToolkit\OpenType\Layout\FeatureParametersReader;
use PDFToolkit\OpenType\Substitution\LayoutReadBudget;
use PDFToolkit\OpenType\Value\Tag;

require dirname(__DIR__).'/vendor/autoload.php';

$input = stream_get_contents(STDIN);
if ($input === false) {
    throw new RuntimeException('Missing input.');
}
/** @var list<array{tag: string, hex: string}> $cases */
$cases = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
$results = [];
foreach ($cases as $case) {
    $bytes = hex2bin($case['hex']);
    if ($bytes === false) {
        throw new InvalidArgumentException('Invalid hex input.');
    }
    $model = (new FeatureParametersReader())->read(new BinaryView($bytes), new Tag($case['tag']), new LayoutReadBudget(10000));
    $values = get_object_vars($model);
    if ($model instanceof CharacterVariantParameters) {
        $values['characters'] = array_map(static fn ($point): int => $point->value, $model->characters);
    }
    $results[] = $values;
}
echo json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
