<?php

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationCuttingRuleInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getLabel(): string;

  public function getOffset(): int;

  public function getLength(): int;

  public function getMappingConfiguration(): MappingConfigurationInterface;

  public function setMappingConfiguration(MappingConfigurationInterface $mappingConfiguration);
}