<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatAdvancedString extends FieldCheckFormat
{
  private $indexFichier;

  public function __construct($code, $libelle, $indexFichier, $nullable)
  {
    parent::__construct("string", $code, $libelle, $nullable, []);
    $this->indexFichier= $indexFichier;
  }

  /**
   * @return mixed
   */
  public function getIndexFichier()
  {
    return $this->indexFichier;
  }

  /**
   * @param mixed $indexFichier
   */
  public function setIndexFichier($indexFichier): void
  {
    $this->indexFichier = $indexFichier;
  }





}