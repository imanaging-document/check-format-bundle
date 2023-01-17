<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 28/02/2019
 * Time: 12:19
 */

namespace Imanaging\CheckFormatBundle;

use DateTime;
use Exception;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormat;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvanced;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedAutoIncrement;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedConst;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedDateCustom;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedSaisieManuelle;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedString;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedMultiColumnArray;
use stdClass;

class CheckFormatFile
{

  /**
   * @param array $fields
   * @param array $fieldsAdvanced
   * @param array $lines
   * @return array
   * @throws Exception
   */
  public static function checkFormatFile(Array $fields, Array $fieldsAdvanced, Array $lines, $valuesSaisiesManuelles = []) {
    $result = array(
      'nb_lines' => 0,
      'error' => false,
      'errors_list' => array()
    );

    $errorsList = array();

    $nbLignes = 0;
    foreach ($lines as $line) {
      $nbLignes ++;
      $res = self::checkFormatLine($fields, $fieldsAdvanced, $nbLignes, $line, $valuesSaisiesManuelles);

      if ($res['error']) {
        array_push($errorsList, array('ligne' => $nbLignes, 'errors_list' => $res['errors_list']));
      }
    }

    $result['nb_lines'] = $nbLignes;

    if (count($errorsList) > 0) {
      $result['error'] = true;
      $result['errors_list'] = $errorsList;
    }
    return $result;
  }

  /**
   * @param array $fields
   * @param array $fieldsAdvanced
   * @param array $datas
   * @param bool $returnDataObj
   * @return array
   * @throws Exception
   */
  public static function checkFormatLine(Array $fields, Array $fieldsAdvanced , $index, Array $datas, $valuesSaisiesManuelles = [], $returnDataObj = false){
    if ($returnDataObj) {
      $objData = new stdClass();
    } else {
      $objData = null;
    }
    if (count($fields) == count($datas)) {
      $errorsList = array(
        'classic' => array(),
        'advanced' => array()
      );
      // TODO Gestion fieldsAdvanced
      foreach ($fieldsAdvanced as $fieldAdvanced) {
        $fieldConcat = '';
        if ($fieldAdvanced instanceof FieldCheckFormatAdvanced) {
          foreach ($fieldAdvanced->getFields() as $field) {
            if ($field instanceof FieldCheckFormatAdvancedConst) {
              $fieldConcat .= $field->getConst();
            } elseif ($field instanceof FieldCheckFormatAdvancedDateCustom) {
              $date = new DateTime($field->getModifier());
              $dateFormatted = $date->format($field->getFormat());
              $translatedValue = $fieldAdvanced->getTranslatedValue($dateFormatted);
              $transformedValue = $fieldAdvanced->getTransformedValue($translatedValue);
              $fieldConcat .= $transformedValue;
            } elseif ($field instanceof FieldCheckFormatAdvancedMultiColumnArray) {
              $fieldConcat = [];
              $nbCols = 0;
              foreach ($field->getColumns() as $column) {
                $explodes = $field->getExplodedValues($datas[$column->value]);
                if ($nbCols > 0) {
                  if (count($explodes) <> $nbCols) {
                    // TODO ERROR
                  } else {
                    $nbCols == count($explodes);
                  }
                }
                foreach ($explodes as $key => $explode) {
                  if (!array_key_exists($key, $fieldConcat)){
                    $fieldConcat[$key] = [];
                  }
                  $fieldConcat[$key][$column->code] = $explode;
                }
              }
            }elseif ($field instanceof FieldCheckFormatAdvancedString) {
              $translatedValue = $field->getTranslatedValue($datas[$field->getIndexFichier()]);
              $transformedValue = $field->getTransformedValue($translatedValue);
              $libelleNullable = ($field->isNullable()) ? "OUI" : "NON";
              $libelleTranslated = (!is_null($transformedValue)) ? $transformedValue : "valeur NULL";
              $libelleTranslatedValue = ($transformedValue !== $datas[$field->getIndexFichier()]) ? (' "( Traduction : "' . $libelleTranslated . '" )') : "";
              if (!$field->validFormat($transformedValue)) {
                array_push($errorsList['advanced'], array('field' => $fieldAdvanced->getLibelle() . " -> " . $field->getLibelle(), 'error_message' => 'la valeur "' . $datas[$field->getIndexFichier()] . '"' . $libelleTranslatedValue  . ' ne respecte pas le format "' . $field->getType() . '" (Nullable : ' . $libelleNullable . ') '));
              } else {
                // on vérifie les valeurs possibles
                if (!$field->validValuesPossibles($transformedValue)) {
                  array_push($errorsList['advanced'], array('field' => $fieldAdvanced->getLibelle() . " -> " . $field->getLibelle(), 'error_message' => 'la valeur "' . $datas[$field->getIndexFichier()] . '"' . $libelleTranslatedValue  . ' ne respecte pas la liste des valeurs possibles (' . $field->getValeursPossiblesString() . ').'));
                } else {
                  $fieldConcat .= $field->getValue($transformedValue);
                }
              }
            } elseif ($field instanceof FieldCheckFormatAdvancedSaisieManuelle) {
              $fieldConcat .= $valuesSaisiesManuelles[$field->getIdValueAvance()];
            } elseif ($field instanceof FieldCheckFormatAdvancedAutoIncrement) {
              $fieldConcat .= $index;
            } else {
              return array(
                'error' => true,
                'errors_list' => array('classic' => array(array()), 'advanced' => array('field' => null, 'error_message' => 'Ce format d\'objet n\'est pas géré.'))
              );
            }
          }

          if (!$fieldAdvanced->validValuesPossibles($fieldConcat)) {
            array_push($errorsList['advanced'], array('field' => $fieldAdvanced->getLibelle(), 'error_message' => 'la valeur "' . $fieldConcat  . '" ne respecte pas la liste des valeurs possibles (' . $fieldAdvanced->getValeursPossiblesString() . ').'));
          } else {
            if ($returnDataObj) {
              $lib = str_replace(' ', '_', $fieldAdvanced->getCode());
              $objData->{$lib} = $fieldConcat;
            }
          }
        }
      }

      for ($i = 0; $i < count($fields); $i++) {
        $fieldTemp = $fields[$i];
        if ($fieldTemp instanceof FieldCheckFormat) {
          $translatedValue = $fieldTemp->getTranslatedValue($datas[$i]);
          $transformedValue = $fieldTemp->getTransformedValue($translatedValue);
          $libelleNullable = ($fieldTemp->isNullable()) ? "OUI" : "NON";
          $libelleTranslated = (!is_null($transformedValue)) ? $transformedValue : "valeur NULL";
          $libelleTranslatedValue = ($transformedValue !== $datas[$i]) ? ' "( Traduction : "' . $libelleTranslated . '" )' : "";
          if (!$fieldTemp->validFormat($transformedValue)) {
            array_push($errorsList['classic'], array('field' => $fieldTemp->getLibelle(), 'error_message' => 'la valeur "' . $datas[$i] . '"' . $libelleTranslatedValue  . ' ne respecte pas le format "' . $fieldTemp->getType() . '" (Nullable : ' . $libelleNullable . ') '));
          } else {
            // on vérifie les valeurs possibles
            if (!$fieldTemp->validValuesPossibles($translatedValue)) {
              array_push($errorsList['classic'], array('field' => $fieldTemp->getLibelle(), 'error_message' => 'la valeur "' . $datas[$i] . '"' . $libelleTranslatedValue  . ' ne respecte pas la liste des valeurs possibles ('.$fieldTemp->getValeursPossiblesString().').'));
            } else {
              if ($returnDataObj) {
                $lib = str_replace(' ','_',$fieldTemp->getCode());
                $objData->{$lib} = $fieldTemp->getValue($transformedValue);
              }
            }
          }
        } else {
          return array(
            'error' => true,
            'errors_list' => array('classic' => array(array('field' => null, 'error_message' => 'Une erreur est survenue, veuillez contacter un administrateur')), 'advanced' => [])
          );
        }
      }

      if (count($errorsList['classic']) > 0 || count($errorsList['advanced']) > 0) {
        return array(
          'error' => true,
          'errors_list' => $errorsList
        );
      }
      return array(
        'error' => false,
        'obj_data' => $objData
      );
    } else {
      return [
        'error' => true,
        'errors_list' => [
          'classic' => [
            [
              'field' => null,
              'error_message' => 'Le nombre de colonne est différent du nombre prévu (' . count($datas) . ' / ' . count($fields) . " attendus )"
            ]
          ],
          'advanced' => []
        ]
      ];
    }
  }

  /**
   * @param array $fields
   * @param array $fieldsAdvanced
   * @param array $datas
   * @return mixed|null
   * @throws Exception
   */
  public static function getObjByLine(Array $fields, Array $fieldsAdvanced, $index, Array $datas, $valuesSaisiesManuelles = []){
    $res = self::checkFormatLine($fields, $fieldsAdvanced, $index, $datas, $valuesSaisiesManuelles, true);
    if (!$res['error']) {
      return $res['obj_data'];
    } else {
      return null;
    }
  }

  /**
   * @param $string
   * @return string
   */
  public static function encodeToUtf8($string) {
    return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "UTF-8, ISO-8859-1, ISO-8859-15", true));
  }
}
