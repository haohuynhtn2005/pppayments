<?php
namespace ShieldPpPayment\Library;

use ShieldPpPayment\Job\TelegramMessageJob;
use ShieldPpPayment\Library\CsPluginConfig;
// use PaypalShield\Job\TelegramMessageJob;

class Logger {
  private static string $channel = 'default';
  // private static string $logFile = DIR_LOGS . 'paypal_shield.log';
  // private static string $logDir = DIR_LOGS . 'paypal_shield/';
  private static string $logFile = '';
  private static string $logDir = '';
  private static string $logName = 'shield';
  private static int $maxFiles = 0;
  private static bool $daily = true;
  private static array $levelList = [
    'debug' => 100,
    'info' => 200,
    'notice' => 300,
    'warning' => 400,
    'error' => 500,
    'critical' => 600,
    'alert' => 700,
    'emergency' => 800,
  ];

  /**
   * Must be called once in startup: Log::init();
   */
  public static function init(): void {
    $startupFile = CsPluginConfig::get('plugin.plugin_startup_file');
    $pluginRootDir = dirname($startupFile);
    $logDir = "$pluginRootDir/logs/";
    self::$logDir = "$logDir";
    self::$logFile = "$logDir/shield.log";
    if (self::$daily) {
      if (!is_dir(self::$logDir)) {
        mkdir(self::$logDir, 0777, true);
      }
      self::cleanup();
      return;
    }

    if (!is_file(self::$logFile)) {
      touch(self::$logFile);
      return;
    }

    // keep last 2MB
    $maxSize = 2 * 1024 * 1024;
    if (filesize(self::$logFile) > $maxSize) {
      $fp = fopen(self::$logFile, 'r');
      fseek($fp, -$maxSize, SEEK_END);
      $data = fread($fp, $maxSize);
      fclose($fp);
      file_put_contents(self::$logFile, $data);
    }
  }

  private static function write(string $level, string $message): void {
    if (!self::shouldLog($level)) {
      return;
    }

    $dateTime = gmdate('Y-m-d H:i:s');
    $formattedMessage = strtoupper($level) . " [$dateTime]: " . $message;
    $formattedMessage = "[$dateTime] " . strtoupper($level) . ": $message";

    switch (self::$channel) {
      case 'telegram':
        TelegramMessageJob::dispatch($formattedMessage);
        break;
      default:
        $file = self::$daily ? self::getLogFile() : self::$logFile;
        file_put_contents($file, $formattedMessage . PHP_EOL, FILE_APPEND);
    }

    self::$channel = 'default';
  }

  private static function cleanup(): void {
    if (self::$maxFiles <= 0) {
      return;
    }

    $files = glob(self::$logDir . self::$logName . '-*.log');
    if (\count($files) <= self::$maxFiles) {
      return;
    }

    sort($files);
    $remove = \array_slice($files, 0, count($files) - self::$maxFiles);
    foreach ($remove as $file) {
      unlink($file);
    }
  }

  private static function getLogFile(): string {
    return self::$logDir . self::$logName . '-' . gmdate('Y-m-d') . '.log';
  }

  private static function shouldLog(string $level): bool {
    $currentLevel = CsPluginConfig::get('plugin.logLevel');
    $currentLevelNum =
      self::$levelList[$currentLevel] ?? self::$levelList['debug'];
    return self::$levelList[$level] >= $currentLevelNum;
  }

  public static function channel(string $name): self {
    self::$channel = $name;
    return new self();
  }

  public static function emergency(string $message): void {
    self::write('emergency', $message);
  }

  public static function alert(string $message): void {
    self::write('alert', $message);
  }

  public static function critical(string $message): void {
    self::write('critical', $message);
  }

  public static function error(string $message): void {
    self::write('error', $message);
  }

  public static function warning(string $message): void {
    self::write('warning', $message);
  }

  public static function notice(string $message): void {
    self::write('notice', $message);
  }

  public static function info(string $message): void {
    self::write('info', $message);
  }

  public static function debug(string $message): void {
    self::write('debug', $message);
  }
}
