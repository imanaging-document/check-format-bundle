<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 22/02/2021
 * Time: 11:45
 */

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatAdvancedMultiColumnArray extends FieldCheckFormat
{
  private $columns;
  private $delimiter;

  public function __construct($code, $libelle, $delimiter, $columns)
  {
    parent::__construct("multi_column_objet", $code, $libelle, false, []);
    $this->delimiter = $delimiter;
    $this->columns= $columns;
  }

  public function validFormat($value) {
    return true;
  }

  /**
   * @return mixed
   */
  public function getColumns()
  {
    return $this->columns;
  }

  /**
   * @param mixed $columns
   */
  public function setColumns($columns): void
  {
    $this->columns = $columns;
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

  public function getExplodedValues($value) {
    if ($this->delimiter == "\\n" || $this->delimiter == "\\r\\n") {
      return preg_split('/\n|\r\n?/', $value);
    } else {
      return explode($this->delimiter, $value);;
    }
  }
}
