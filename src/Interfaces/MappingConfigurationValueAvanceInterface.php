<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 05/06/2019
 * Time: 12:07
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationValueAvanceInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getOrder(): int;

  public function setOrder(int $order);

  public function getValue(): string;

  public function getMappingConfigurationValue();

  public function setMappingConfigurationValue($mappingConfigurationValue);

  public function getMappingConfigurationValueAvanceType();

  public function setMappingConfigurationValueAvanceType($mappingConfigurationValueAvanceType);

  public function initFromImportFileValueAvance(array $importFileValueAvance);

  public function getExportValueAvance() : array;
}
