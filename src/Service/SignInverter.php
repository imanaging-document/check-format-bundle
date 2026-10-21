<?php

namespace Imanaging\CheckFormatBundle\Service;

use Imanaging\CheckFormatBundle\Exception\TransformationException;

/** Change only the sign, without rounding or casting the amount to a number. */
final class SignInverter
{
  public static function convert($value): ?string
  {
    if ($value === null) {
      return null;
    }
    if (!is_string($value) && !is_int($value)) {
      throw new TransformationException('La valeur doit être un nombre écrit en chiffres pour inverser son signe.');
    }
    $value = preg_replace('/^[\s\x{00A0}\x{202F}]+|[\s\x{00A0}\x{202F}]+$/u', '', (string) $value);
    if ($value === null) {
      throw new TransformationException('Encodage du nombre non valide.');
    }
    if ($value === '') {
      return '';
    }
    if (!preg_match('/^([+-]?)([0-9]+(?:[., \x{00A0}\x{202F}][0-9]+)*)$/uD', $value, $parts)) {
      throw new TransformationException('Nombre non valide pour inverser son signe.');
    }
    // All representations of zero stay unsigned.
    if (!preg_match('/[1-9]/', $parts[2])) {
      return '0';
    }
    return ($parts[1] === '-' ? '' : '-') . $parts[2];
  }
}
