<?php

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueAvanceSaisieManuelleInterface extends MappingConfigurationValueAvanceInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getMappingConfigurationValue();

  public function setMappingConfigurationValue($mappingConfigurationValue);

  public function getMappingConfigurationValueAvanceType();

  public function setMappingConfigurationValueAvanceType($mappingConfigurationValueAvanceType);
}