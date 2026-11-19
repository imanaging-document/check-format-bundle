<?php

namespace Imanaging\CheckFormatBundle\Service;

final class CsvStructureComparison
{
  /** @param array<int, string|null> $expected */
  public static function compare(array $expected, array $actual): ?array
  {
    if ($expected === []) {
      return null;
    }
    $count = max(array_keys($expected)) + 1;
    $differences = [];
    $hasEncodingIssue = false;
    $hasOtherDifferences = false;
    foreach ($actual as $header) {
      if (is_string($header) && preg_match('//u', $header) !== 1) {
        $hasEncodingIssue = true;
      }
    }
    for ($index = 0; $index < min($count, count($actual)); $index++) {
      $known = array_key_exists($index, $expected) && $expected[$index] !== null;
      $value = $actual[$index] ?? '';
      if (!$known || $expected[$index] !== $value) {
        $invalidEncoding = is_string($value) && preg_match('//u', $value) !== 1;
        $displayedValue = $invalidEncoding
        ? htmlspecialchars_decode(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_QUOTES) : $value;
        $encodingOnly = $known && $invalidEncoding && $expected[$index] === $displayedValue;
        if (!$encodingOnly) {
          $hasOtherDifferences = true;
        }
        $differences[] = [
          'position' => $index + 1,
          'expected' => $known ? $expected[$index] : null,
          'actual' => $value,
          'encoding_issue' => $invalidEncoding,
          'encoding_only' => $encodingOnly,
        ];
      }
    }
    if ($count === count($actual) && $differences === []) {
      return null;
    }
    return [
      'expected_count' => $count,
      'actual_count' => count($actual),
      'differences' => $differences,
      'has_encoding_issue' => $hasEncodingIssue,
      'has_other_differences' => $hasOtherDifferences,
    ];
  }
}
