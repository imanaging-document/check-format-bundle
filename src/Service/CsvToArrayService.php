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
    $handle = $this->openCsv($filename);
    try {
      while (($row = fgetcsv($handle, 100000, $delimiter, '"', '')) !== false) {
        $data[] = $row;
      }
    } finally {
      fclose($handle);
    }
    return $data;
  }

  public function readHeader(string $filename, string $delimiter = ','): array
  {
    $handle = $this->openCsv($filename);
    try {
      $header = fgetcsv($handle, 100000, $delimiter, '"', '');
      return is_array($header) ? $header : [];
    } finally {
      fclose($handle);
    }
  }

  private function openCsv(string $filename)
  {
    $handle = fopen($filename, 'rb');
    if ($handle === false) {
      throw new \RuntimeException('Impossible de lire le fichier CSV.');
    }

    $bom = fread($handle, 4);
    rewind($handle);
    if (substr($bom, 0, 4) === "\xFF\xFE\x00\x00" || substr($bom, 0, 4) === "\x00\x00\xFE\xFF") {
      fclose($handle);
      throw new \RuntimeException('L’encodage UTF-32 du fichier CSV n’est pas pris en charge.');
    }
    if (substr($bom, 0, 2) === "\xFF\xFE" || substr($bom, 0, 2) === "\xFE\xFF") {
      $encoding = substr($bom, 0, 2) === "\xFF\xFE" ? 'UTF-16LE' : 'UTF-16BE';
      fseek($handle, 2);
    } else {
      $hasBom = substr($bom, 0, 3) === "\xEF\xBB\xBF";
      $encoding = 'UTF-8';
      $invalidWindows1252 = false;
      while (($line = fgets($handle)) !== false) {
        if (preg_match('//u', $line) !== 1) {
          $encoding = 'Windows-1252';
        }
        if (preg_match('/[\x81\x8D\x8F\x90\x9D]/', $line) === 1) {
          $invalidWindows1252 = true;
        }
      }
      if ($encoding !== 'UTF-8' && ($hasBom || $invalidWindows1252)) {
        fclose($handle);
        throw new \RuntimeException('L’encodage du fichier CSV est invalide ou non pris en charge.');
      }
      fseek($handle, $hasBom ? 3 : 0);
    }
    if ($encoding !== 'UTF-8' && stream_filter_append($handle, 'convert.iconv.' . $encoding . '/UTF-8', STREAM_FILTER_READ) === false) {
      fclose($handle);
      throw new \RuntimeException('Impossible de convertir le fichier CSV en UTF-8.');
    }
    return $handle;
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
