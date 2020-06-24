<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormatInt extends FieldCheckFormat
{
  public function __construct($libelle, $nullable, $valeursPossibles)
  {
    parent::__construct("integer", $libelle, $nullable, $valeursPossibles);
  }

  public function validFormat($value) {
    if ($this->validNullable($value)) {
      if (!is_int($value)) {
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  }

}