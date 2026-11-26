<?php

namespace Imanaging\CheckFormatBundle\Service;

use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedString;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatTransformation;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface;

class DistinctMappingValues
{
  /** Returns null when there are at least 20 values or the mapping cannot be previewed. */
  public function collect(MappingConfigurationValueInterface $mappingValue, array $lines): ?array
  {
    $parts = [];
    if ($mappingValue->getFichierIndex() === null) {
      foreach ($mappingValue->getMappingConfigurationValueAvances() as $part) {
        if ($part instanceof MappingConfigurationValueAvanceTextInterface) {
          $parts[] = $part->getValue();
        } elseif ($part instanceof MappingConfigurationValueAvanceFileInterface) {
          $field = new FieldCheckFormatAdvancedString('', '', $part->getFichierIndex(), true);
          foreach (AdvancedPartTransformation::forPart($mappingValue->getMappingConfigurationValueTransformations(), $part) as $transformation) {
            $field->addTransformation(new FieldCheckFormatTransformation(
              $transformation->getTransformation(), $transformation->getNbCaract(), $transformation->getTransformationOptions()
            ));
          }
          foreach ($part->getMappingConfigurationValueAvanceFileTransformations() as $transformation) {
            $field->addTransformation(new FieldCheckFormatTransformation(
              $transformation->getTransformation(), $transformation->getNbCaract(), $transformation->getTransformationOptions()
            ));
          }
          $parts[] = $field;
        } else {
          return null;
        }
      }
      if (!$parts) {
        return null;
      }
    }

    $values = [];
    foreach ($lines as $line) {
      if ($mappingValue->getFichierIndex() !== null) {
        if (!array_key_exists($mappingValue->getFichierIndex(), $line)) {
          return null;
        }
        $value = (string) $line[$mappingValue->getFichierIndex()];
      } else {
        $value = '';
        foreach ($parts as $part) {
          if (is_string($part)) {
            $value .= $part;
          } else {
            $index = $part->getIndexFichier();
            if (!array_key_exists($index, $line)) {
              return null;
            }
            $value .= $part->getValue($part->getTransformedValue($part->getTranslatedValue($line[$index])));
          }
        }
      }
      $values[base64_encode($value)] = $value;
      if (count($values) >= 20) {
        return null;
      }
    }
    return array_values($values);
  }
}
