<?php

namespace ShieldPpPayment\Console\Commands\Base;

abstract class Command {
  protected string $signature = '';
  protected string $description = '';

  abstract public function handle(array $args = []): void;

  public function getSignature(): string {
    return $this->signature;
  }

  public function getName(): string {
    return explode(' ', $this->signature)[0];
  }

  public function getDescription(): string {
    return $this->description;
  }

  protected function line(string $text): void {
    echo $text;
  }
}
