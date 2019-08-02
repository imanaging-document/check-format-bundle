<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 28/02/2019
 * Time: 12:19
 */

namespace Imanaging\CheckFormatBundle;

use Imanaging\CheckFormatBundle\Entity\FieldCheckFormat;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvanced;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedConst;
use Imanaging\CheckFormatBundle\Entity\FieldCheckFormatAdvancedString;
use stdClass;

class CheckFormatFile
{

  /**
   * @param array $fields
   * @param array $fieldsAdvanced
   * @param array $lines
   * @return array
   */
  public static function checkFormatFile(Array $fields, Array $fieldsAdvanced, Array $lines) {
    $result = array(
      'nb_lines' => 0,
      'error' => false,
      'errors_list' => array()
    );

    $errorsList = array();

    $nbLignes = 0;
    foreach ($lines as $line) {
      $nbLignes ++;
      $res = self::checkFormatLine($fields, $fieldsAdvanced, $line);

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
   */
  public static function checkFormatLine(Array $fields, Array $fieldsAdvanced ,Array $datas, $returnDataObj = false){
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
            } elseif ($field instanceof FieldCheckFormatAdvancedString) {
              $translatedValue = $field->getTranslatedValue($datas[$field->getIndexFichier()]);
              $transformedValue = $field->getTransformedValue($translatedValue);
              if (!$field->validFormat($transformedValue)) {
                $libelleNullable = ($field->isNullable()) ? "OUI" : "NON";
                $libelleTranslated = (!is_null($transformedValue)) ? $transformedValue : "valeur NULL";
                $libelleTranslatedValue = ($transformedValue !== $datas[$field->getIndexFichier()]) ? ' "( Traduction : "' . $libelleTranslated . '" )' : "";
                array_push($errorsList['advanced'], array('field' => $fieldAdvanced->getLibelle() . " -> " . $field->getLibelle(), 'error_message' => 'la valeur "' . $datas[$field->getIndexFichier()] . '"' . $libelleTranslatedValue  . ' ne respecte pas le format "' . $field->getType() . '" (Nullable : ' . $libelleNullable . ') '));
              } else {
                $fieldConcat .= $field->getValue($translatedValue);
              }
            } else {
              return array(
                'error' => true,
                'errors_list' => array('classic' => array(array(), 'advanced' => array('field' => null, 'error_message' => 'Ce format d\'objet n\'est pas géré.')), 'advanced' => [])
              );
            }
          }
          if ($returnDataObj) {
            $lib = str_replace(' ', '_', $fieldAdvanced->getCode());
            $objData->{$lib} = $fieldConcat;
          }
        }
      }


      for ($i = 0; $i < count($fields); $i++) {
        $fieldTemp = $fields[$i];
        if ($fieldTemp instanceof FieldCheckFormat) {
          $translatedValue = $fieldTemp->getTranslatedValue($datas[$i]);
          $transformedValue = $fieldTemp->getTransformedValue($translatedValue);
          if (!$fieldTemp->validFormat($transformedValue)) {
            $libelleNullable = ($fieldTemp->isNullable()) ? "OUI" : "NON";
            $libelleTranslated = (!is_null($transformedValue)) ? $transformedValue : "valeur NULL";
            $libelleTranslatedValue = ($transformedValue !== $datas[$i]) ? ' "( Traduction : "' . $libelleTranslated . '" )' : "";
            array_push($errorsList['classic'], array('field' => $fieldTemp->getLibelle(), 'error_message' => 'la valeur "' . $datas[$i] . '"' . $libelleTranslatedValue  . ' ne respecte pas le format "' . $fieldTemp->getType() . '" (Nullable : ' . $libelleNullable . ') '));
          } else {
            if ($returnDataObj) {
              $lib = str_replace(' ','_',$fieldTemp->getCode());
              $objData->{$lib} = $fieldTemp->getValue($transformedValue);
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
      return array(
        'error' => true,
        'errors_list' => array('classic' => array(array('field' => null, 'error_message' => 'Le nombre de colonne est différent du nombre prévu (' . count($datas) . ' / ' . count($fields) . " attendus )")), 'advanced' => [])
      );
    }
  }

  /**
   * @param array $fields
   * @param array $fieldsAdvanced
   * @param array $datas
   * @return mixed|null
   */
  public static function getObjByLine(Array $fields, Array $fieldsAdvanced, Array $datas){
    $res = self::checkFormatLine($fields, $fieldsAdvanced, $datas, true);

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
  private function encodeToUtf8($string) {
    return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "UTF-8, ISO-8859-1, ISO-8859-15", true));
  }
}