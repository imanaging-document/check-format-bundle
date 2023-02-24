<?php

namespace Imanaging\CheckFormatBundle\Interfaces;

interface ConfigurationExportAutomatiqueMappingInterface
{
  public function getId(): int;
  public function getTypeExport(): string;
  public function getCheminRepertoire(): string;
  public function getFilename(): string;
  public function getSftpUrl(): string;
  public function getSftpPort(): string;
  public function getSftpLogin(): string;
  public function getSftpPassword(): string;
}