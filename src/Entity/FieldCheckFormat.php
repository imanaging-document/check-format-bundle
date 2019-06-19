<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


use Imanaging\CheckFormatBundle\Enum\TransformationEnum;

class FieldCheckFormat
{

  private $type;
  private $libelle;
  private $code;
  private $nullable;
  private $longueur_min;
  private $longueur_max;
  private $translations;
  private $transformations;


  public function __construct($type, $code, $libelle, $nullable, $longueurMin = null, $longueurMax = null) {
    $this->type= $type;
    $this->code = $code;
    $this->libelle = $libelle;
    $this->nullable = $nullable;
    $this->longueur_min = $longueurMin;
    $this->longueur_max = $longueurMax;
    $this->translations = array();
    $this->transformations = array();
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
  public function getCode()
  {
    return $this->code;
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

  public function addTransformation(FieldCheckFormatTransformation $transformation) {
    array_push($this->transformations, $transformation);
  }

  public function getTransformedValue($value) {
    $transformedValue = $value;
    foreach ($this->transformations as $transformation) {
      if ($transformation instanceof FieldCheckFormatTransformation) {
        switch ($transformation->getTransformation()) {
          case TransformationEnum::ADD_CHARACTER_ZERO_START:
            while(strlen($transformedValue) < $transformation->getNbCaract()) {
              $transformedValue = "0" . $transformedValue;
            }
            break;
          case TransformationEnum::ADD_CHARACTER_ZERO_END:
            while(strlen($transformedValue) < $transformation->getNbCaract()) {
              $transformedValue .= "0";
            }
            break;
          case TransformationEnum::ADD_CHARACTER_SPACE_START:
            while(strlen($transformedValue) < $transformation->getNbCaract()) {
              $transformedValue = " " . $transformedValue;
            }
            break;
          case TransformationEnum::ADD_CHARACTER_SPACE_END:
            while(strlen($transformedValue) < $transformation->getNbCaract()) {
              $transformedValue .= " ";
            }
            break;
          case TransformationEnum::REMOVE_CHARACTER_ZERO_START:
            while(substr($transformedValue, 0,1) == "0") {
              $transformedValue = substr($transformedValue, 1);
            }
            break;
        }
      }
    }
    return $transformedValue;
  }

  /**
   * @param $value
   * @return mixed
   */
  public function getValue($value) {
    return $this->encodeToUtf8($value);
  }

  /**
   * @param $string
   * @return string
   */
  private function encodeToUtf8($string) {
    return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "UTF-8, ISO-8859-1, ISO-8859-15", true));
  }
}