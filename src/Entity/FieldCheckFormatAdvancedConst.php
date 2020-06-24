<?php
/**
 * Created by PhpStorm.
 * User: antonin
 * Date: 28/02/2019
 * Time: 12:50
 */

namespace Imanaging\CheckFormatBundle\Entity;



class FieldCheckFormatAdvancedConst extends FieldCheckFormat
{
  private $const;

  public function __construct($code, $libelle, $const)
  {
    parent::__construct("const", $code, $libelle, false, []);
    $this->const = $const;
  }

  public function validFormat($value) {
    return true;
  }

  /**
   * @return mixed
   */
  public function getConst()
  {
    return $this->const;
  }




}