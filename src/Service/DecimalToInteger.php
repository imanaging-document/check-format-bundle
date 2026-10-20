<?php

namespace Imanaging\CheckFormatBundle\Service;

use Imanaging\CheckFormatBundle\Exception\TransformationException;

/** Exact decimal conversion: never cast a formatted number to float or int. */
final class DecimalToInteger
{
  public static function normalizeOptions(?array $options = null): array
  {
    $options = array_replace([
      'decimal_separator' => ',',
      'thousands_separator' => '.',
      'decimal_places' => 2,
    ], $options ?? []);

    if (!in_array($options['decimal_separator'], [',', '.'], true)
      || !in_array($options['thousands_separator'], ['', '.', ',', ' '], true)) {
      throw new TransformationException('Séparateur numérique non valide.');
    }
    if ($options['decimal_separator'] === $options['thousands_separator']) {
      throw new TransformationException('Les séparateurs décimal et de milliers doivent être différents.');
    }
    $places = $options['decimal_places'];
    if ((!is_int($places) && !is_string($places))
      || !preg_match('/^[0-9]+$/D', (string) $places)
      || filter_var($places, FILTER_VALIDATE_INT) === false) {
      throw new TransformationException('Le nombre de décimales doit être un entier positif ou nul.');
    }
    return [
      'decimal_separator' => $options['decimal_separator'],
      'thousands_separator' => $options['thousands_separator'],
      'decimal_places' => (int) $places,
    ];
  }

  public static function convert($value, ?array $options = null): ?string
  {
    $options = self::normalizeOptions($options);
    if ($value === null) {
      return null;
    }
    if (!is_string($value) && !is_int($value)) {
      throw new TransformationException('La valeur doit être un nombre décimal écrit en chiffres.');
    }
    $value = preg_replace('/^[\s\x{00A0}\x{202F}]+|[\s\x{00A0}\x{202F}]+$/u', '', (string) $value);
    if ($value === null) {
      throw new TransformationException('Encodage du nombre décimal non valide.');
    }
    if ($value === '') {
      return '';
    }

    $thousands = $options['thousands_separator'];
    if ($thousands === ' ') {
      $value = str_replace(["\u{00A0}", "\u{202F}"], ' ', $value);
    }
    $integerPattern = '[0-9]+';
    if ($thousands !== '') {
      $integerPattern = '(?:[0-9]+|[0-9]{1,3}(?:' . preg_quote($thousands, '/') . '[0-9]{3})+)';
    }
    $pattern = '/^([+-]?)(' . $integerPattern . ')(?:'
      . preg_quote($options['decimal_separator'], '/') . '([0-9]+))?$/D';
    if (!preg_match($pattern, $value, $parts)) {
      throw new TransformationException('Nombre décimal non valide : vérifier les chiffres et les séparateurs configurés.');
    }
    $fraction = $parts[3] ?? '';
    if (strlen($fraction) > $options['decimal_places']) {
      throw new TransformationException('La valeur dépasse les ' . $options['decimal_places'] . ' décimales configurées (aucun arrondi).');
    }

    $integer = $thousands === '' ? $parts[2] : str_replace($thousands, '', $parts[2]);
    $result = ltrim($integer . str_pad($fraction, $options['decimal_places'], '0'), '0');
    return $result === '' ? '0' : ($parts[1] === '-' ? '-' : '') . $result;
  }
}
