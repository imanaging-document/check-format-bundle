<?php
/**
 * Created by PhpStorm.
 * User: Antonin
 * Date: 20/11/2020
 * Time: 15:58
 */

namespace Imanaging\CheckFormatBundle\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelToArrayService
{
  public function __construct()
  {
  }

  public function convert($filename)
  {
    $data = [];
    $spreadsheet = IOFactory::load($filename);
    if ($spreadsheet instanceof Spreadsheet) {
      $worksheet = $spreadsheet->getActiveSheet();

      $highestRow = $worksheet->getHighestRow();
      $highestColumn = $worksheet->getHighestColumn();
      $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
      $data =[];
      for($row=1; $row <= $highestRow ; $row++){
        $rowIndex = $row-1;
        $rowArray = [];
        for($col = 1; $col <= $highestColumnIndex; $col++){
          $value = $worksheet->getCellByColumnAndRow($col,$row)->getValue();
          array_push($rowArray,$value);
        }
        $data[$rowIndex] = $rowArray;
      }
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
