<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 05/06/2019
 * Time: 12:07
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueAvanceFileInterface extends MappingConfigurationValueAvanceInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getMappingConfigurationValue();

  public function setMappingConfigurationValue($mappingConfigurationValue);

  public function getMappingConfigurationValueAvanceType();

  public function setMappingConfigurationValueAvanceType($mappingConfigurationValueAvanceType);

  public function getFichierIndex();

  public function setFichierIndex(int $fichierIndex);

  public function getFichierEntete(): string;

  public function setFichierEntete(string $fichierEntete);

  public function getValue(): string;

  public function getMappingConfigurationValueAvanceFileTransformations();

  public function setMappingConfigurationValueAvanceFileTransformations($mappingConfigurationValueTransformations);
}