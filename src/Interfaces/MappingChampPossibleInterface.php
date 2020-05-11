<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 29/05/2017
 * Time: 09:52
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingChampPossibleInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getTable(): string;

  public function setTable(string $table);

  public function getData(): string;

  public function setData(string $data);

  public function getLibelle(): string;

  public function setLibelle(string $libelle);

  public function getDescription(): string;

  public function setDescription(string $libelle);

  public function getType(): string;

  public function setType(string $type);

  public function isVisible(): bool;

  public function setVisible(bool $visible);

  public function isObligatoire(): bool;

  public function setObligatoire(bool $obligatoire);

  public function isNullable(): bool;

  public function setNullable(bool $nullable);

  public function isIntegrationLocal(): bool;

  public function setIntegrationLocal(bool $integrationLocal);

  public function getMappingConfigurationType(): MappingConfigurationTypeInterface;

  public function setMappingConfigurationType(MappingConfigurationTypeInterface $mappingConfigurationType);
}