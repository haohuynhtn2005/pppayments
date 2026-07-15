<?php

namespace ShieldPpPayment\Console\Commands;

use ShieldPpPayment\Console\Commands\Base\Command;
use ShieldPpPayment\Library\CsPluginConfig;

class ListCommand extends Command {
  protected string $signature = 'list';
  protected string $description = 'List all available commands';
  /** @var array<class-string<Command>> */
  protected array $commands = [];

  /**
   * @param array<class-string<Command>> $commands
   */
  public function __construct(array $commands = []) {
    $commands = CsPluginConfig::get('script.commands');
    $this->commands = $commands;
  }

  public function handle(array $args = []): void {
    $this->line("Available commands:\n");

    foreach ($this->commands as $commandClass) {
      /** @var Command $command */
      $command = new $commandClass();

      $name = $command->getName();
      $desc = $command->getDescription();

      \printf("  %-20s %s\n", $name, $desc);
    }
  }
}
