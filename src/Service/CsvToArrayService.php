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
    $data = [];

    if (($handle = fopen($filename, 'r')) !== false) {
      $row = null;
      while (($row = fgetcsv($handle, 100000, $delimiter)) !== FALSE)
      {
         $data[] = $row;
      }
      fclose($handle);
    }
    return $data;
  }

  public function getFromTxt($filename) : array
  {
    if (!file_exists($filename) || !is_readable($filename)) {
      return false;
    }
    $data = [];

    if (($handle = fopen($filename, 'r')) !== false) {
      while(!feof($handle)) {
        $data[] = fgets($handle);
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
