<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 28/02/2019
 * Time: 12:19
 */

namespace Imanaging\CheckFormatBundle;

use Imanaging\CheckFormatBundle\Entity\FieldCheckFormat;
use stdClass;

class CheckFormatFile
{

  /**
   * @param array $fields
   * @param array $lines
   * @return array
   */
  public static function checkFormatFile(Array $fields, Array $lines) {
    $result = array(
      'nb_lines' => 0,
      'error' => false,
      'errors_list' => array()
    );

    $errorsList = array();

    $nbLignes = 0;
    foreach ($lines as $line) {
      $nbLignes ++;
      $res = self::checkFormatLine($fields, $line);

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
   * @param array $datas
   * @param bool $returnDataObj
   * @return array
   */
  public static function checkFormatLine(Array $fields, Array $datas, $returnDataObj = false){
    if ($returnDataObj) {
      $objData = new stdClass();
    } else {
      $objData = null;
    }
    if (count($fields) == count($datas)) {
      $errorsList = array();

      for ($i = 0; $i < count($fields); $i++) {
         $fieldTemp = $fields[$i];
         if ($fieldTemp instanceof FieldCheckFormat) {
           $translatedValue = $fieldTemp->getTranslatedValue($datas[$i]);
           if (!$fieldTemp->validFormat($translatedValue)) {
             $libelleTranslatedValue = ($translatedValue !== $datas[$i]) ? ' ( Traduction : ' . $translatedValue . ' )' : "";
              array_push($errorsList, array('field' => $fieldTemp->getLibelle(), 'error_message' => 'la valeur "' . $datas[$i]  . $libelleTranslatedValue  . '" ne respecte pas le format "' . $fieldTemp->getType() . '"'));
           } else {
             if ($returnDataObj) {
               $lib = str_replace(' ','_',$fieldTemp->getLibelle());
               $objData->{$lib} = $translatedValue;
             }
           }
         } else {
           return array(
             'error' => true,
             'errors_list' => array(array('field' => null, 'error_message' => 'Une erreur est survenue, veuillez contacter un administrateur'))
           );
         }
      }

      if (count($errorsList) > 0) {
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
        'errors_list' => array(array('field' => null, 'error_message' => 'Le nombre de colonne est différent du nombre prévu (' . count($datas) . ' / ' . count($fields) . " attendus )"))
      );
    }
  }

  /**
   * @param array $fields
   * @param array $datas
   * @return mixed|null
   */
  public static function getObjByLine(Array $fields, Array $datas){
    $res = self::checkFormatLine($fields, $datas, true);
    if (!$res['error']) {
      return $res['obj_data'];
    } else {
      return null;
    }
  }
}