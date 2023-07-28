<?php

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationSkippingRuleInterface
{
  const TYPE_FIRSTS_CHAR_VALUES = 'firsts_chars_in_values';

  public function getId(): int;

  public function setId(int $id);

  public function getType(): string;

  public function getDatas(): array;

  public function getMappingConfiguration(): MappingConfigurationInterface;

  public function setMappingConfiguration(MappingConfigurationInterface $mappingConfiguration);
}