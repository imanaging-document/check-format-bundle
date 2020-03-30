<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 05/06/2019
 * Time: 12:07
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueTranslationInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getValue(): string;

  public function setValue(string $value);

  public function getTranslation();

  public function setTranslation($translation);

  public function getMappingConfigurationValue();

  public function setMappingConfigurationValue($mappingConfigurationValue);
}