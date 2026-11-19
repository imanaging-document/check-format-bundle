<?php

namespace Imanaging\CheckFormatBundle\Service;

use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;

final class MappingStructurePreview
{
  public static function build(MappingConfigurationInterface $configuration): array
  {
    $columns = [];
    $advanced = [];
    $xmlFields = [];
    $isXml = $configuration->getSourceType() === MappingConfigurationInterface::SOURCE_TYPE_XML;
    $options = $isXml ? ($configuration->getSourceOptions() ?? []) : [];
    $associatedCount = 0;

    foreach ($configuration->getMappingConfigurationValues() as $value) {
      if (!$value instanceof MappingConfigurationValueInterface) {
        continue;
      }
      $index = $value->getFichierIndex();
      $code = $value->getMappingCode();
      if ($index === null) {
        if (is_string($code) && $code !== '') {
          $advanced[] = $code;
          $associatedCount++;
        }
      } elseif (!$isXml && (int) $index >= 0) {
        $columns[(int) $index] = ['header' => $value->getFichierEntete(), 'code' => $code];
        if (is_string($code) && $code !== '') {
          $associatedCount++;
        }
      }
    }

    if ($isXml) {
      foreach ($options['field_mappings'] ?? [] as $field) {
        if (is_array($field) && is_string($field['mapping_code'] ?? null) && $field['mapping_code'] !== '') {
          $xmlFields[] = $field['mapping_code'];
          $associatedCount++;
        }
      }
    } elseif ($columns !== []) {
      $lastIndex = max(array_keys($columns));
      for ($index = 0; $index <= $lastIndex; $index++) {
        if (!array_key_exists($index, $columns)) {
          $columns[$index] = ['header' => null, 'code' => null];
        }
      }
      ksort($columns);
    }

    return [
      'is_xml' => $isXml,
      'columns' => $columns,
      'advanced' => $advanced,
      'xml_fields' => $xmlFields,
      'row_xpath' => $options['row_xpath'] ?? null,
      'associated_count' => $associatedCount,
    ];
  }
}
