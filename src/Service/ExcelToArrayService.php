<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 20/11/2020
 * Time: 15:58
 */

namespace Imanaging\CheckFormatBundle\Service;

class ExcelToArrayService
{
  public function __construct()
  {
  }

  public function convert($filePath)
  {
    $directoryPath = dirname($filePath);
    $filename = basename($filePath);
    $config   = ['path' => $directoryPath];
    $excel    = new \Vtiful\Kernel\Excel($config);
    return $excel->openFile($filename)
      ->openSheet()
      ->setGlobalType(\Vtiful\Kernel\Excel::TYPE_STRING)
      ->getSheetData();
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
