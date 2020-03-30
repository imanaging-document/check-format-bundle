<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 05/06/2019
 * Time: 12:07
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueTransformationInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getTransformation(): string;

  public function setTransformation(string $transformation);

  public function getNbCaract(): string;

  public function setNbCaract(string $nbCaract);

  public function getMappingConfigurationValue();

  public function setMappingConfigurationValue($mappingConfigurationValue);
}