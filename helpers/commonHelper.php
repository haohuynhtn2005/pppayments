<?php
// if (!function_exists('log_error')) {
//   function log_error($file_name, $message)
//   {
//     $log_dir = __DIR__ . "/../logs/";
//     if (!file_exists($log_dir)) {
//       mkdir($log_dir, 0777, true);
//     }

//     $log_file = $log_dir . "{$file_name}_" . date("Y-m-d") . ".txt";
//     $log_data = date('Y-m-d H:i:s') . " - " . $message . "\n";
//     file_put_contents($log_file, $log_data, FILE_APPEND);
//   }
// }

if (!function_exists('plugin_custom_log')) {
  function plugin_custom_log($message = '', $fileName = '')
  {
    if (is_array($message) || is_object($message)) {
      $message = print_r($message, true);
    }

    $plugin_dir = plugin_dir_path(__FILE__);
    $log_dir = $plugin_dir . '../logs/';
    if (!file_exists($log_dir)) {
      mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . 'log-' . date('Y-m-d') . '.log';
    $formatted_message = '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
  }
}

if (!function_exists('telegram_push_log')) {

  if (!defined('TELEGRAM_PUSH_LOG_ENABLED')) {
    define('TELEGRAM_PUSH_LOG_ENABLED', true);
  }

  if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', '8795456576:AAE2oW0GZj5oURtbHwg97PM2X-S8b5lGdfc');
  }

  if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', '-1001626566210');
  }

  /**
   * telegram push log
   */
  function telegram_push_log($message, $idTele = null, $threadId = '88548'): void
  {
    if (!TELEGRAM_PUSH_LOG_ENABLED) {
      return;
    }

    if (empty(TELEGRAM_BOT_TOKEN) && empty(TELEGRAM_CHAT_ID)) {
      return;
    }

    if (is_array($message)) {
      $message = print_r($message, true);
    }

    $host = 'localhost';
    if ($_SERVER['HTTP_HOST']) {
      $host = strtoupper($_SERVER['HTTP_HOST']);
    }

    $message = $host . "|" . $message;
    $bot_token = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
      'chat_id' => $idTele !== null ? $idTele : TELEGRAM_CHAT_ID,
      'text' => $message
    ];

    if ($threadId !== null) {
      $data['message_thread_id'] = (int) $threadId;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = null;
    if ($response === false || $httpCode >= 400) {
      $curlError = curl_error($ch);
      plugin_custom_log("Telegram push log failed: Unable to send message.");
    }
  }
}

if (!function_exists('encodePmCode')) {
  function encodePmCode(string $link)
  {
    $encode = rtrim(strtr(base64_encode($link), '+/', '-_'), '=');
    $encode = strrev($encode);
    return $encode;
  }
}

if (!function_exists('decodePmCode')) {
  function decodePmCode(string $code)
  {
    $reversedStr = strrev($code);
    $padding = strlen($reversedStr) % 4;
    if ($padding > 0) {
      $reversedStr .= str_repeat('=', 4 - $padding);
    }
    $decrypted = base64_decode(strtr($reversedStr, '-_', '+/'));

    return $decrypted;
  }
}
