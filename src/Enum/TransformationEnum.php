<?php

namespace Imanaging\CheckFormatBundle\Enum;

abstract class TransformationEnum
{
  const ADD_CHARACTER_ZERO_START = "add_character_zero_start";
  const ADD_CHARACTER_ZERO_END = "add_character_zero_end";
  const ADD_CHARACTER_SPACE_START = "add_character_space_start";
  const ADD_CHARACTER_SPACE_END = "add_character_space_end";
  const REMOVE_CHARACTER_ZERO_START = "remove_character_zero_start";
  const ADD_CHARACTER_ZERO_START_IF_NOT_EMPTY = "add_character_zero_start_if_not_empty";
  const TRUNCATE = "truncate";

  /** @var array user friendly named type */
  protected static $transformationName = [
    self::ADD_CHARACTER_ZERO_START    => 'Ajouter caractère 0 au début',
    self::ADD_CHARACTER_ZERO_END=> 'Ajouter caractère 0 à la fin',
    self::ADD_CHARACTER_SPACE_START => 'Ajouter caractère espace au début',
    self::ADD_CHARACTER_SPACE_END => 'Ajouter caractère espace à la fin',
    self::REMOVE_CHARACTER_ZERO_START => 'Supprimer les caractères 0 au début',
    self::ADD_CHARACTER_ZERO_START_IF_NOT_EMPTY => 'Ajouter caractère 0 au début (si la valeur n\'est pas vide)',
    self::TRUNCATE => 'Tronquer la valeur'
  ];

  /**
   * @param  string $typeShortName
   * @return string
   */
  public static function getTransformationName($typeShortName)
  {
    if (!isset(static::$transformationName[$typeShortName])) {
      return "Unknown type ($typeShortName)";
    }

    return static::$transformationName[$typeShortName];
  }

  public static function getTransformationByCode($code) {
//    foreach ()
    return false;
  }

  /**
   * @return array<string>
   */
  public static function getAvailableTransformations()
  {
    return [
      self::ADD_CHARACTER_ZERO_START,
      self::ADD_CHARACTER_ZERO_END,
      self::ADD_CHARACTER_SPACE_START,
      self::ADD_CHARACTER_SPACE_END,
      self::REMOVE_CHARACTER_ZERO_START,
      self::ADD_CHARACTER_ZERO_START_IF_NOT_EMPTY,
      self::TRUNCATE
    ];
  }

  public static function getAvailableTransformationsWithLibelle() {
    return static::$transformationName;
  }
}
