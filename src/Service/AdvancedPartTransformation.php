<?php

namespace Imanaging\CheckFormatBundle\Service;

use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface;

class AdvancedPartTransformation
{
  public static function isScoped(MappingConfigurationValueTransformationInterface $transformation): bool
  {
    $options = $transformation->getTransformationOptions();
    return is_array($options) && isset($options['advanced_part']) && is_array($options['advanced_part']);
  }

  public static function matches(MappingConfigurationValueTransformationInterface $transformation, MappingConfigurationValueAvanceFileInterface $part): bool
  {
    if (!self::isScoped($transformation)) {
      return false;
    }
    $scope = $transformation->getTransformationOptions()['advanced_part'];
    return isset($scope['ordre'], $scope['fichier_index'])
      && (int) $scope['ordre'] === $part->getOrdre()
      && (int) $scope['fichier_index'] === $part->getFichierIndex();
  }
  public static function forPart(iterable $transformations, MappingConfigurationValueAvanceFileInterface $part): array
  {
    $matches = [];
    foreach ($transformations as $transformation) {
      if ($transformation instanceof MappingConfigurationValueTransformationInterface && self::matches($transformation, $part)) {
        $matches[] = $transformation;
      }
    }
    usort($matches, static function ($left, $right) {
      return $left->getId() <=> $right->getId();
    });
    return $matches;
  }

}
