<?php

namespace Imanaging\CheckFormatBundle\Interfaces;

interface ConfigurationImportAutomatiqueMappingInterface
{
  public function getId(): int;
  public function getCheminRepertoire(): string;
  public function getSftpUrl(): string;
  public function getSftpPort(): string;
  public function getSftpLogin(): string;
  public function getSftpPassword(): string;
  public function isObligatoire(): bool;
  public function getMappingConfiguration() : MappingConfigurationInterface;
}