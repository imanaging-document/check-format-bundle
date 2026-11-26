<?php

use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface;
use Imanaging\CheckFormatBundle\Service\DistinctMappingValues;
use PHPUnit\Framework\TestCase;

class DistinctMappingValuesTest extends TestCase
{
  public function testClassicValuesIncludeEmptyAndRespectThreshold(): void
  {
    $mapping = $this->createMock(MappingConfigurationValueInterface::class);
    $mapping->method('getFichierIndex')->willReturn(0);
    $service = new DistinctMappingValues();
    self::assertSame(['', 'Non concernÈ', '0'], $service->collect($mapping, [[''], ['Non concernÈ'], [''], ['0']]));
    self::assertCount(19, $service->collect($mapping, array_map(fn ($i) => [(string) $i], range(1, 19))));
    self::assertNull($service->collect($mapping, array_map(fn ($i) => [(string) $i], range(1, 20))));
    self::assertNull($service->collect($mapping, [[]]));
  }

  public function testAdvancedValuesUsePartTransformationsAndConcatenation(): void
  {
    $mapping = $this->createMock(MappingConfigurationValueInterface::class);
    $mapping->method('getFichierIndex')->willReturn(null);
    $file = $this->createMock(MappingConfigurationValueAvanceFileInterface::class);
    $file->method('getFichierIndex')->willReturn(0);
    $file->method('getOrdre')->willReturn(1);
    $file->method('getMappingConfigurationValueAvanceFileTransformations')->willReturn([]);
    $separator = $this->createMock(MappingConfigurationValueAvanceTextInterface::class);
    $separator->method('getValue')->willReturn('-');
    $other = $this->createMock(MappingConfigurationValueAvanceFileInterface::class);
    $other->method('getFichierIndex')->willReturn(1);
    $other->method('getOrdre')->willReturn(3);
    $other->method('getMappingConfigurationValueAvanceFileTransformations')->willReturn([]);
    $mapping->method('getMappingConfigurationValueAvances')->willReturn([$file, $separator, $other]);
    $transformation = $this->createMock(MappingConfigurationValueTransformationInterface::class);
    $transformation->method('getTransformation')->willReturn('replace');
    $transformation->method('getNbCaract')->willReturn('0');
    $transformation->method('getTransformationOptions')->willReturn([
      'advanced_part' => ['ordre' => 1, 'fichier_index' => 0], 'search' => 'x', 'replace' => 'y',
    ]);
    $mapping->method('getMappingConfigurationValueTransformations')->willReturn([$transformation]);
    self::assertSame(['y-12', '-'], (new DistinctMappingValues())->collect($mapping, [['x', '12'], ['', ''], ['x', '12']]));
  }
}
