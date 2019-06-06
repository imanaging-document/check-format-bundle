<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormat
{

  private $type;
  private $libelle;
  private $nullable;
  private $longueur_min;
  private $longueur_max;
  private $translations;


  public function __construct($type, $libelle, $nullable, $longueurMin = null, $longueurMax = null) {
    $this->type= $type;
    $this->libelle = $libelle;
    $this->nullable = $nullable;
    $this->longueur_min = $longueurMin;
    $this->longueur_max = $longueurMax;
    $this->translations = array();
  }

  /**
   * @return mixed
   */
  public function getType() {
    return $this->type;
  }

  /**
   * @return mixed
   */
  public function getLibelle() {
    return $this->libelle;
  }

  /**
   * @return boolean
   */
  public function isNullable() {
    return $this->nullable;
  }

  /**
   * @param $value
   * @return bool
   */
  public function validNullable($value) {
    if (!$this->nullable && (is_null($value) || $value == "" )) {
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

  public function getTranslatedValue($value) {
    $translatedValue = $value;
    foreach ($this->translations as $translation) {
      if ($translation instanceof FieldCheckFormatTranslation) {
        if ($translation->getSearchValue() == $value) {
          $translatedValue = $translation->getTranslation();
        }
      }
    }
    return $translatedValue;
  }

  public function addTranslation(FieldCheckFormatTranslation $translation) {
    array_push($this->translations, $translation);
  }

}