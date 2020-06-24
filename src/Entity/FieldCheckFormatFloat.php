<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 23/06/2020
 * Time: 10:27
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormatFloat extends FieldCheckFormat
{

  public function __construct($code, $libelle, $nullable, $valeursPossibles) {
    parent::__construct('boolean', $code, $libelle, $nullable, $valeursPossibles);
  }

  /**
   * @param $value
   * @return bool
   */
  public function validFormat($value) {
    if ($this->validNullable($value)) {
      $resIsFloat = $this->isFloat($value);
      if ($resIsFloat['success']) {
        // par défaut toutes les values valides le type si nullable OK et longueur OK
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

  private function isFloat($value) {
    return ['success' => is_float($value)];
  }
}
