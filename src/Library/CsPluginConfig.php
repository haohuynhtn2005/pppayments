<?php
namespace ShieldPpPayment\Library;

class CsPluginConfig {
  public static function init() {
    self::$dir = realpath(__DIR__ . '/../config');
    self::initExtConfig();
  }

  private static function initExtConfig() {
    
  }

  protected static array $cache = [];
  protected static string $dir;

  public static function get(string $key, $default = null) {
    $parts = explode('.', $key);

    $fileParts = [];
    for ($i = 1; $i <= \count($parts); $i++) {
      $fileParts = \array_slice($parts, 0, $i);
      $filePath = self::$dir . '/' . implode('/', $fileParts) . '.php';
      $fileKey = implode('.', $fileParts);
      $cacheExists = \array_key_exists($fileKey, self::$cache);
      if ($cacheExists) {
        $keys = [$fileKey, ...\array_slice($parts, $i)];
        return self::getValueByKeys($keys, $default);
      }
      if (file_exists($filePath)) {
        self::loadFile($fileKey, $filePath);
        $keys = [$fileKey, ...\array_slice($parts, $i)];
        return self::getValueByKeys($keys, $default);
      }
    }
    return self::getValueByKeys($parts, $default);
  }

  public static function set(string $key, $value): void {
    $parts = explode('.', $key);
    $fileParts = [];
    $config = [];

    for ($i = 1; $i <= \count($parts); $i++) {
      $fileParts = \array_slice($parts, 0, $i);
      $filePath = self::$dir . '/' . implode('/', $fileParts) . '.php';
      if (file_exists($filePath)) {
        $fileKey = implode('.', $fileParts);
        $config = self::loadFile($fileKey, $filePath);
        $configPath = \array_slice($parts, $i);

        self::setValueByPath($config, $configPath, $value);

        // Update cache
        self::$cache[$fileKey] = $config;
        return;
      }
    }

    $fileKey = implode('/', $fileParts) ?: $parts[0];
    self::setValueByPath(
      $config,
      \array_slice($parts, \count($fileParts)),
      $value,
    );
    self::$cache[$fileKey] = $config;
  }

  protected static function loadFile(string $key, string $filePath): array {
    if (\array_key_exists($key, self::$cache)) {
      return self::$cache[$key];
    }
    return self::$cache[$key] = require $filePath;
  }

  protected static function getValueByKeys(array $keys, $default) {
    $config = self::$cache;
    foreach ($keys as $key) {
      if (!\is_array($config) || !\array_key_exists($key, $config)) {
        return $default;
      }
      $config = $config[$key];
    }

    return $config;
  }

  protected static function setValueByPath(
    array &$config,
    array $keys,
    $value,
  ): void {
    $current = &$config;
    foreach ($keys as $key) {
      if (!isset($current[$key]) || !\is_array($current[$key])) {
        $current[$key] = [];
      }
      $current = &$current[$key];
    }
    $current = $value;
  }
}
