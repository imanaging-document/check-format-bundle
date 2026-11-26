<?php

require_once __DIR__ . '/../src/Service/CsvToArrayService.php';
require_once __DIR__ . '/../src/Service/CsvStructureComparison.php';

use Imanaging\CheckFormatBundle\Service\CsvStructureComparison;
use Imanaging\CheckFormatBundle\Service\CsvToArrayService;

$converter = new CsvToArrayService();
$expected = [['Nom', 'Tél'], ['Élodie', 'coût; 20 €']];
$utf8 = "Nom;Tél\nÉlodie;\"coût; 20 €\"\n";
$cases = [
  'UTF-8' => $utf8,
  'UTF-8 BOM' => "\xEF\xBB\xBF" . $utf8,
  'Windows-1252' => iconv('UTF-8', 'Windows-1252', $utf8),
  'UTF-16LE BOM' => "\xFF\xFE" . iconv('UTF-8', 'UTF-16LE', $utf8),
  'accent après en-tête ASCII' => "Nom;Tel\n" . iconv('UTF-8', 'Windows-1252', "Élodie;oui\n"),
];
$file = tempnam(sys_get_temp_dir(), 'csv');
try {
  foreach ($cases as $name => $content) {
    file_put_contents($file, $content);
    $wanted = $name === 'accent après en-tête ASCII' ? [['Nom', 'Tel'], ['Élodie', 'oui']] : $expected;
    if ($converter->convert($file, ';') !== $wanted || $converter->readHeader($file, ';') !== $wanted[0]) {
      throw new RuntimeException('Échec de conversion : ' . $name);
    }
    if (CsvStructureComparison::compare($wanted[0], $converter->readHeader($file, ';')) !== null) {
      throw new RuntimeException('Comparaison incorrecte : ' . $name);
    }
  }
  file_put_contents($file, "A\n\x81\n");
  try {
    $converter->convert($file, ';');
    throw new RuntimeException('Un encodage invalide doit être rejeté.');
  } catch (RuntimeException $e) {
    if ($e->getMessage() === 'Un encodage invalide doit être rejeté.') {
      throw $e;
    }
  }
} finally {
  unlink($file);
}
echo "Encodages CSV vérifiés.\n";
