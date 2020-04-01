<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 29/05/2017
 * Time: 09:52
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueInterface
{
  public function __construct();

  public function getId();

  public function setId(int $id);

  public function getFichierIndex();

  public function setFichierIndex(int $fichierIndex);

  public function getFichierEntete();

  public function setFichierEntete(string $fichierEntete);

  public function getMappingCode();

  public function setMappingCode($mappingCode);

  public function getMappingType();

  public function setMappingType($mappingType);

  public function getMappingConfiguration();

  public function setMappingConfiguration($mappingConfiguration);

  public function getMappingConfigurationValueTranslations();

  public function setMappingConfigurationValueTranslations($mappingConfigurationValueTranslations);

  public function addMappingConfigurationValueTranslation(MappingConfigurationValueTranslationInterface $translation);

  public function getMappingConfigurationValueTransformations();

  public function setMappingConfigurationValueTransformations($mappingConfigurationValueTransformations);

  public function getMappingCongurationValueTranslationsFormatted();

  public function getMappingCongurationValueTransformationsFormatted();

  public function getMappingConfigurationValueAvances();

  public function setMappingConfigurationValueAvances($mappingConfigurationValueAvances);

  public function __toString() : string;

  public function getTypeMapping() : string;
}
