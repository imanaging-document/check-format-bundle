<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 22/02/2021
 * Time: 10:52
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormatArray extends FieldCheckFormat
{
  private $delimiter;

  public function __construct($code, $libelle, $nullable, $valeursPossibles, $delimiter)
  {
    parent::__construct("array", $code, $libelle, $nullable, $valeursPossibles);
    $this->delimiter = $delimiter;
  }

  /**
   * @return mixed
   */
  public function getDelimiter()
  {
    return $this->delimiter;
  }

  /**
   * @param mixed $delimiter
   */
  public function setDelimiter($delimiter): void
  {
    $this->delimiter = $delimiter;
  }

  public function validFormat($value) {
    if ($this->validNullable($value)) {
      if ($this->isNull($value)){
        return true;
      }
      return $this->isValidArray($value, $this->delimiter);
    } else {
      return false;
    }
  }

  /**
   * @param $value
   * @param $format
   * @return bool
   */
  private function isValidArray($value, $delimiter) {
    return true;
  }

  /**
   * @param DateTime $value
   * @return string
   */
  public function getValue($value) {
    return explode($this->getTranslatedDelimiter(), $value);
  }

  private function getTranslatedDelimiter() {
    if ($this->delimiter == "\\n" || $this->delimiter == "\\r\\n") {
      return PHP_EOL;
    } else {
      return $this->delimiter;
    }
  }
}