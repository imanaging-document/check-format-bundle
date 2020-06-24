<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


use DateTime;

class FieldCheckFormatDate extends FieldCheckFormat
{
  private $format;

  public function __construct($code, $libelle, $nullable, $valeursPossibles, $format = null)
  {
    parent::__construct("datetime", $code, $libelle, $nullable, $valeursPossibles);
    $this->format = $format;
  }

  public function setFormat($format) {
    $this->format = $format;
  }

  public function getFormat() {
    return $this->format;
  }

  public function validFormat($value) {
    if ($this->validNullable($value)) {
      if ($this->isNull($value)){
        return true;
      }

      if (!is_null($this->format)) {
        return $this->isValidDate($value, $this->format);
      } else {
        // on boucle sur les formats de date possible pour faire le check
        foreach ($this->getFormatsDate() as $format) {
          if ($this->isValidDate($value, $format)) {
            return true;
          }
        }

        return false;
      }
    } else {
      return false;
    }
  }

  /**
   * @param $value
   * @param $format
   * @return bool
   */
  private function isValidDate($value, $format) {
    if ($this->isNullable() && is_null($value)) {
      return true;
    }

    $date = DateTime::createFromFormat($format, $value);
    if (!is_null($date) && $date instanceof \DateTime) {
      return true;
    } else {
      return false;
    }
  }

  private function getFormatsDate() {
    return array(
      'Ymd',
      'Y-m-d',
      'dmY',
      'd-m-Y'
    );
  }

  /**
   * @param DateTime $value
   * @return string
   */
  public function getValue($value) {
    $dateTemp = DateTime::createFromFormat($this->format, $value);
    if ($dateTemp instanceof DateTime) {
      return $dateTemp->format('YmdHis');
    } else {
      return "";
    }
  }
}