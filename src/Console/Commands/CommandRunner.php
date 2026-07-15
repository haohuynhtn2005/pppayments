<?php
namespace ShieldPpPayment\Console\Commands;

use ShieldPpPayment\Console\Commands\Base\Command;
use ShieldPpPayment\Library\CsPluginConfig;

class CommandRunner {
  public static function run() {
    $commands = CsPluginConfig::get('script.commands');

    $input = $_SERVER['argv'] ?? [];
    array_shift($input);

    $commandName = $input[0] ?? null;

    if (!$commandName) {
      echo 'No command provided.' . PHP_EOL;
      exit(1);
    }

    $command = null;
    foreach ($commands as $commandClass) {
      /** @var Command $command */
      $cmdClass = new $commandClass();
      if ($cmdClass->getName() === $commandName) {
        $command = $cmdClass;
        break;
      }
    }
    if (!$command) {
      echo "Command not found: {$commandName}" . PHP_EOL;
      exit(1);
    }

    $args = [];
    $options = [];
    preg_match_all('/\{([^}]+)\}/', $command->getSignature(), $matches);
    $argIndex = 1;
    foreach ($matches[1] as $param) {
      if (!str_starts_with($param, '--')) {
        $args[$param] = $input[$argIndex] ?? null;
        $argIndex++;
      } else {
        $isValueOption = str_ends_with($param, '=');
        $name = ltrim($param, '-');
        $name = rtrim($name, '=');

        if (!$isValueOption) {
          $options[$name] = in_array("--{$name}", $input, true);
        } else {
          $options[$name] = null;
          foreach ($input as $item) {
            if (str_starts_with($item, "--{$name}=")) {
              $options[$name] = substr($item, strlen("--{$name}="));
              break;
            }
          }
        }
      }
    }
    /** @var Command $command */
    $command->handle(array_merge($args, $options));
    exit(0);
  }
}
