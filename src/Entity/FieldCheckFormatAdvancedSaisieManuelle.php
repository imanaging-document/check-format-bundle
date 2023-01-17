<?php

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatAdvancedSaisieManuelle extends FieldCheckFormat
{
  private int $idValueAvance;

  public function __construct($libelle)
  {
    parent::__construct("string", '', $libelle, false, []);
  }

  /**
   * @return int
   */
  public function getIdValueAvance() : int
  {
    return $this->idValueAvance;
  }

  /**
   * @param mixed $idValueAvance
   */
  public function setIdValueAvance(int $idValueAvance): void
  {
    $this->idValueAvance = $idValueAvance;
  }


}