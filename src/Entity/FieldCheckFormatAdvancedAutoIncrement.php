<?php

namespace Imanaging\CheckFormatBundle\Entity;


class FieldCheckFormatAdvancedAutoIncrement extends FieldCheckFormat
{

  public function __construct($libelle)
  {
    parent::__construct("string", '', $libelle, false, []);
  }
}