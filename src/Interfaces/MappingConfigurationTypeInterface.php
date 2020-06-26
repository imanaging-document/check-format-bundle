<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 29/05/2017
 * Time: 09:52
 */

namespace Imanaging\CheckFormatBundle\Interfaces;

interface MappingConfigurationTypeInterface
{
  public function getId(): int;

  public function setId(int $id);

  public function getLibelle(): string;

  public function setLibelle(string $libelle);

  public function getCode(): string;

  public function setCode(string $code);

  public function getFilesDirectory(): string;

  public function setFilesDirectory(string $code);

  public function getRouteIntegrationFichier(): string;

  public function setRouteIntegrationFichier(string $code);
}