<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;



class FieldCheckFormatAdvancedDateCustom extends FieldCheckFormat
{
  private $format;
  private $modifier;

  public function __construct($code, $libelle, $format, $modifier)
  {
    parent::__construct("date_custom", $code, $libelle, false, []);
    $this->format = $format;
    $this->modifier = $modifier;
  }

  public function validFormat($value) {
    return true;
  }

  /**
   * @return mixed
   */
  public function getFormat()
  {
    return $this->format;
  }

  /**
   * @return mixed
   */
  public function getModifier()
  {
    return $this->modifier;
  }
}
