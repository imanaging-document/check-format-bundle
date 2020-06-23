<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 23/06/2020
 * Time: 10:27
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormatInteger extends FieldCheckFormat
{

  public function __construct($code, $libelle, $nullable) {
    parent::__construct('boolean', $code, $libelle, $nullable);
  }

  /**
   * @param $value
   * @return bool
   */
  public function validFormat($value) {
    if ($this->validNullable($value)) {
      $resIsInt = $this->isInteger($value);
      if ($resIsInt['success']) {
        // par défaut toutes les values valides le type si nullable OK et longueur OK
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  private function isInteger($value) {
    return ['success' => is_int($value)];
  }
}
