<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 05/06/2019
 * Time: 12:07
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueAvanceDateCustomInterface extends MappingConfigurationValueAvanceInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getMappingConfigurationValue();

  public function setMappingConfigurationValue($mappingConfigurationValue);

  public function getMappingConfigurationValueAvanceType();

  public function setMappingConfigurationValueAvanceType($mappingConfigurationValueAvanceType);

  public function getFormat(): string;

  public function setFormat(string $value);

  public function getModifier(): string;

  public function setModifier(string $value);
}
