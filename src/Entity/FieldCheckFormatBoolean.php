<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 23/06/2020
 * Time: 10:27
 */

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatBoolean extends FieldCheckFormat
{

  public function __construct($code, $libelle, $nullable, $valeursPossibles) {
    parent::__construct('boolean', $code, $libelle, $nullable, $valeursPossibles);
  }

  /**
   * @param $value
   * @return mixed
   */
  public function getValue($value) {
    $encodedValue = $this->encodeToUtf8($value);
    $resIsBool = $this->isBoolean($encodedValue);
    if ($resIsBool['success']) {
      return $resIsBool['value'];
    } else {
      return $encodedValue;
    }
  }

  /**
   * @param $value
   * @return bool
   */
  public function validFormat($value) {
    if ($this->validNullable($value)) {
      $resIsBool = $this->isBoolean($value);
      if ($resIsBool['success']) {
        // par défaut toutes les values valides le type si nullable OK et longueur OK
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  private function isBoolean($value) {
    if (in_array(strtolower($value), ['1', 'true', 'oui'])) {
      return ['success' => true, 'value' => true];
    } elseif (in_array(strtolower($value), ['0', 'false', 'non'])) {
      return ['success' => true, 'value' => false];
    }

    return ['success' => false, 'value' => $value];
  }
}
