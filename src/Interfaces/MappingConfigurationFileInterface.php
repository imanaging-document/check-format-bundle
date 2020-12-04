<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 01/07/2020
 * Time: 15:45
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationFileInterface
{
  public function getId(): int;

  public function setId(int $id): void;

  public function getInitialFilename(): string;

  public function setInitialFilename(string $initialFilename): void;

  public function getFilename(): string;

  public function setFilename(string $filename): void;

  public function getDateImport() : \DateTime;

  public function setDateImport(\DateTime $dateImport): void;

  public function getAnalyses();

  public function setAnalyses(string $analyses): void;

  public function getAnalysesPostImport();

  public function setAnalysesPostImport(string $analysesPostImport): void;

  public function getCoreIntegrationId();

  public function setCoreIntegrationId($coreIntegrationId): void;

  public function getMappingConfiguration() : MappingConfigurationInterface;

  public function setMappingConfiguration(MappingConfigurationInterface $mappingConfiguration): void;
}