<?php
/**
 * Created by PhpStorm.
 * User: PC14
 * Date: 26/06/2017
 * Time: 12:09
 */

namespace Imanaging\CheckFormatBundle\Service;

class CsvToArrayService
{
  public function __construct()
  {
  }

  public function convert($filename, $delimiter = ',', $maxLength = 1000)
  {
    if (!file_exists($filename) || !is_readable($filename)) {
      return false;
    }

    $header = true;
    $data = [];

    if (($handle = fopen($filename, 'r')) !== false) {
      while (($row = fgetcsv($handle, $maxLength, $delimiter)) !== false) {
        if (!$header) {
          $header = $row;
        } else {
          array_push($data, $row);
        }
      }
      fclose($handle);
    }
    return $data;
  }

  public function invert($data, $filename)
  {
    $fp = fopen($filename, 'w');
    foreach ($data as $value) {
      foreach ($value as $prop => $val) {
        if (strpos($prop, 'date') !== false) {
          $date = $value[$prop]->format('Y-m-d H:i:s');
          $value[$prop] = $date;
        }
      }
      fputcsv($fp, $value, ';', chr(0));
    }
    fclose($fp);
    return $fp;
  }
}
