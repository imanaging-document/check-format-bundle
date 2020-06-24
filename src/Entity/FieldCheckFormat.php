<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


use Imanaging\CheckFormatBundle\Enum\TransformationEnum;

class FieldCheckFormat extends BaseFieldCheckFormat
{

  private $type;
  private $nullable;
  private $longueur_min;
  private $longueur_max;


  public function __construct($type, $code, $libelle, $nullable, $valeursPossibles, $longueurMin = null, $longueurMax = null) {
    parent::__construct($code, $libelle, $valeursPossibles);
    $this->type= $type;
    $this->nullable = $nullable;
    $this->longueur_min = $longueurMin;
    $this->longueur_max = $longueurMax;
  }

  /**
   * @return mixed
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @return boolean
   */
  public function isNullable() {
    return $this->nullable;
  }

  public function isNull($value)
  {
    return (is_null($value) || $value == "" );
  }

  /**
   * @param $value
   * @return bool
   */
  public function validNullable($value) {
    if (!$this->nullable && ($this->isNull($value) )) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * @param $value
   * @return bool
   */
  public function validLongueur($value) {
    if (!is_null($this->longueur_min)) {
      if (strlen($value) < $this->longueur_min) {
        return false;
      }
    }

    if (!is_null($this->longueur_max)) {
      if (strlen($value) > $this->longueur_max) {
        return false;
      }
    }

    return true;
  }

  /**
   * @param $value
   * @return bool
   */
  public function validFormat($value) {
    if ($this->validNullable($value) && $this->validLongueur($value)) {
      // par défaut toutes les values valides le type si nullable OK et longueur OK
      return true;
    } else {
      return false;
    }
  }
}
