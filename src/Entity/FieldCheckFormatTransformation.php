<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 05/06/2019
 * Time: 17:50
 */

namespace Imanaging\CheckFormatBundle\Entity;

class FieldCheckFormatTransformation
{
  private $transformation;
  private $nbCaract;

  public function __construct($transformation, $nbCaract) {
    $this->transformation= $transformation;
    $this->nbCaract = $nbCaract;
  }

  /**
   * @return mixed
   */
  public function getTransformation()
  {
    return $this->transformation;
  }

  /**
   * @param mixed $transformation
   */
  public function setTransformation($transformation): void
  {
    $this->transformation = $transformation;
  }

  /**
   * @return mixed
   */
  public function getNbCaract()
  {
    return $this->nbCaract;
  }

  /**
   * @param mixed $nbCaract
   */
  public function setNbCaract($nbCaract): void
  {
    $this->nbCaract = $nbCaract;
  }



}