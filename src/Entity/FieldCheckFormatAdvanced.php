<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 12/06/2019
 * Time: 17:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatAdvanced extends BaseFieldCheckFormat
{
  private $regex;
  private $fields;

  public function __construct($code, $libelle, $valuesPossibles, $regex = null)
  {
    parent::__construct($code, $libelle, $valuesPossibles);
    $this->regex = $regex;
    $this->fields = array();
  }

  public function addField(FieldCheckFormat $fieldCheckFormat) {
    array_push($this->fields, $fieldCheckFormat);
  }

  /**
   * @return null
   */
  public function getRegex()
  {
    return $this->regex;
  }

  /**
   * @param null $regex
   */
  public function setRegex($regex): void
  {
    $this->regex = $regex;
  }

  /**
   * @return array
   */
  public function getFields(): array
  {
    return $this->fields;
  }

  /**
   * @param array $fields
   */
  public function setFields(array $fields): void
  {
    $this->fields = $fields;
  }
}
