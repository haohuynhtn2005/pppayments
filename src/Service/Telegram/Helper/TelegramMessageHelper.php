<?php
namespace ShieldPpPayment\Service\Telegram\Helper;

class TelegramMessageHelper
{
  public static function escapeMarkdownV2($str, $context = null)
  {
    switch ($context) {
      // Inside `code` or `pre`
      // Only ` and \ must be escaped
      case 'code':
      case 'pre':
        return preg_replace('/([`\\\\])/', '\\\\$1', $str);

      // Inside inline link (...) part
      case 'link':
        // Only ) and \ must be escaped
        return preg_replace('/([)\\\\])/', '\\\\$1', $str);

      // Normal text
      default:
        return strtr($str, [
          '\\' => '\\\\',
          '_' => '\_',
          '*' => '\*',
          '[' => '\[',
          ']' => '\]',
          '(' => '\(',
          ')' => '\)',
          '~' => '\~',
          '`' => '\`',
          '>' => '\>',
          '#' => '\#',
          '+' => '\+',
          '-' => '\-',
          '=' => '\=',
          '|' => '\|',
          '{' => '\{',
          '}' => '\}',
          '.' => '\.',
          '!' => '\!',
        ]);
    }
  }

  public static function getBeautifulLink()
  {
    $url = home_url();
    $parsed = parse_url($url);
    $path = isset($parsed['path']) ? rtrim($parsed['path'], '/') : '';
    $link = $parsed['host'] . $path;
    return $link;
  }
}
