<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 12/06/2019
 * Time: 17:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatAdvanced
{
  private $code;
  private $libelle;
  private $regex;
  private $fields;

  public function __construct($code, $libelle, $regex = null)
  {
    $this->code = $code;
    $this->libelle = $libelle;
    $this->regex = $regex;
    $this->fields = array();
  }

  public function addField(FieldCheckFormat $fieldCheckFormat) {
    array_push($this->fields, $fieldCheckFormat);
  }

  /**
   * @return mixed
   */
  public function getCode()
  {
    return $this->code;
  }

  /**
   * @param mixed $code
   */
  public function setCode($code): void
  {
    $this->code = $code;
  }

  /**
   * @return mixed
   */
  public function getLibelle()
  {
    return $this->libelle;
  }

  /**
   * @param mixed $libelle
   */
  public function setLibelle($libelle): void
  {
    $this->libelle = $libelle;
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