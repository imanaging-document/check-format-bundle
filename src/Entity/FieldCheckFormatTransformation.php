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
  private ?array $options;

  public function __construct($transformation, $nbCaract, ?array $options = null) {
    $this->transformation= $transformation;
    $this->nbCaract = $nbCaract;
    $this->options = $options;
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

  public function getOptions(): ?array
  {
    return $this->options;
  }

  public function setOptions(?array $options): void
  {
    $this->options = $options;
  }

}
