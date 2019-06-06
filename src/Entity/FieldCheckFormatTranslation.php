<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 05/06/2019
 * Time: 17:50
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormatTranslation
{
  private $searchValue;
  private $translation;

  public function __construct($searchValue, $translation) {
    $this->searchValue= $searchValue;
    $this->translation = $translation;
  }

  /**
   * @return mixed
   */
  public function getSearchValue()
  {
    return $this->searchValue;
  }

  /**
   * @param mixed $searchValue
   */
  public function setSearchValue($searchValue): void
  {
    $this->searchValue = $searchValue;
  }

  /**
   * @return mixed
   */
  public function getTranslation()
  {
    return $this->translation;
  }

  /**
   * @param mixed $translation
   */
  public function setTranslation($translation): void
  {
    $this->translation = $translation;
  }


}