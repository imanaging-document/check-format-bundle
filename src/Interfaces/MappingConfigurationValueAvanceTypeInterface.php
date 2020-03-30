<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 05/06/2019
 * Time: 12:07
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueAvanceTypeInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getCode(): string;

  public function setCode(string $code);

  public function getLibelle(): string;

  public function setLibelle(string $libelle);
}