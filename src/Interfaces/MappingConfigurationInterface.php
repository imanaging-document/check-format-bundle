<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 29/05/2017
 * Time: 09:52
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationInterface
{
  public function getId();

  public function setId(int $id);

  public function getLibelle();

  public function setLibelle(string $libelle);

  public function isActive();

  public function setActive(bool $active);

  public function getType() : MappingConfigurationTypeInterface;

  public function setType(MappingConfigurationTypeInterface $type);

  public function getMappingConfigurationValues();

  public function setMappingConfigurationValues($mappingConfigurationValues);

  public function getMappingConfigurationCuttingRules();

  public function getFormattedConfiguration() : array;
}