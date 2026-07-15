<?php

use ShieldPpPayment\Console\Commands\ExportExtentionCommand;
use ShieldPpPayment\Console\Commands\ListCommand;

return [
  'commands' => [
    ListCommand::class,
    ExportExtentionCommand::class,
  ],
];
