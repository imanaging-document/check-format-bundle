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

  public function convert($filename, $delimiter = ',')
  {
    if (!file_exists($filename) || !is_readable($filename)) {
      return false;
    }
    $header = true;
    $data = [];
    if (($handle = fopen($filename, 'r')) !== false) {
      $row = null;
      while (($raw_string = fgets($handle)) !== false) {
        $data[] = explode($delimiter, strtok($raw_string, "\n"));
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
