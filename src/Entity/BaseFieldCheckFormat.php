<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


use Imanaging\CheckFormatBundle\Enum\TransformationEnum;

class BaseFieldCheckFormat
{
  protected $code;
  protected $libelle;
  protected $translations;
  protected $transformations;


  public function __construct($code, $libelle) {
    $this->code = $code;
    $this->libelle = $libelle;
    $this->translations = array();
    $this->transformations = array();
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
   * @param $value
   * @return bool
   */
  public function validFormat($value) {
    return true;
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
