<?php
namespace Dell\WpShieldpp\Helper;

final class StrHelper
{
  public static function toString(mixed $val): string
  {
    if (\is_object($val)) {
      if (method_exists($val, '__toString')) {
        return (string) $val;
      }
      return '{}';
    }
    if (\is_array($val)) {
      return '[]';
    }

    if ($val == null) {
      return '';
    }
    if (\is_bool($val)) {
      return $val ? 'true' : 'false';
    }
    return (string) $val;
  }
}