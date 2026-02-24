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

    $maxRetries = 3;
    $retryCount = 0;

    while (true) {
      try {
        return $excel->openFile($filename)
          ->openSheet()
          ->setGlobalType(\Vtiful\Kernel\Excel::TYPE_STRING)
          ->getSheetData();
      } catch (\Exception $e) {
        $retryCount++;
        if ($retryCount >= $maxRetries) {
          $perms = file_exists($filePath) ? substr(sprintf('%o', fileperms($filePath)), -4) : 'N/A';
          $owner = file_exists($filePath) ? fileowner($filePath) : 'N/A';
          $size = file_exists($filePath) ? filesize($filePath) : 'N/A';
          $exists = file_exists($filePath) ? 'yes' : 'no';
          
          throw new \Exception(
            sprintf(
              "Failed to open file after %d attempts. Path: %s. Exists: %s. Perms: %s. Owner: %s. Size: %s. Original error: %s",
              $maxRetries,
              $filePath,
              $exists,
              $perms,
              $owner,
              $size,
              $e->getMessage()
            )
          );
        }
        usleep(100000); // 100ms
      }
    }
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
