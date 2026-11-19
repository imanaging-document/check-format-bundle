<?php

require_once __DIR__ . '/../src/Service/CsvStructureComparison.php';

use Imanaging\CheckFormatBundle\Service\CsvStructureComparison;

$cases = [
  'nouveau mapping' => [[], ['A'], null],
  'en-têtes identiques' => [['A', 'B'], ['A', 'B'], null],
  'colonne manquante' => [['A', 'B'], ['A'], [2, 1, []]],
  'colonne supplémentaire' => [['A'], ['A', 'B'], [1, 2, []]],
  'colonnes inversées' => [['A', 'B'], ['B', 'A'], [2, 2, [1, 2]]],
  'nom différent' => [['A', 'B'], ['A', 'b'], [2, 2, [2]]],
  'position sans référence' => [[0 => 'A', 2 => 'C'], ['A', 'B', 'C'], [3, 3, [2]]],
  'référence null' => [['A', null], ['A', 'B'], [2, 2, [2]]],
  'fichier vide' => [['A', 'B'], [], [2, 0, []]],
];
foreach ($cases as $name => [$expected, $actual, $wanted]) {
  $result = CsvStructureComparison::compare($expected, $actual);
  $summary = $result === null ? null : [
    $result['expected_count'], $result['actual_count'], array_column($result['differences'], 'position'),
  ];
  if ($summary !== $wanted) {
    fwrite(STDERR, 'Échec : ' . $name . PHP_EOL);
    exit(1);
  }
}
$encoding = CsvStructureComparison::compare(['T�l'], ["T\xe9l"]);
if (!$encoding['has_encoding_issue'] || $encoding['has_other_differences'] || !$encoding['differences'][0]['encoding_only']) {
  throw new RuntimeException('L’encodage invalide doit être distingué d’un nom différent.');
}
$mixed = CsvStructureComparison::compare(['T�l', 'Nom'], ["T\xe9l", 'Prénom']);
if (!$mixed['has_encoding_issue'] || !$mixed['has_other_differences']) {
  throw new RuntimeException('Les différences de nom doivent rester signalées avec un problème d’encodage.');
}
$changed = CsvStructureComparison::compare(['Adresse'], ["T\xe9l"]);
if (!$changed['has_encoding_issue'] || !$changed['has_other_differences'] || $changed['differences'][0]['encoding_only']) {
  throw new RuntimeException('Un nom différent et mal encodé ne doit pas être réduit à un problème d’encodage.');
}
echo (count($cases) + 3) . " cas de comparaison CSV vérifiés.\n";
