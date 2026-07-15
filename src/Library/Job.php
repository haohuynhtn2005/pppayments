<?php
namespace ShieldPpPayment\Library;

use ShieldPpPayment\Library\CsPluginConfig;

abstract class Job {

  public function __construct(...$arguments) {
  }

  /**
   * @template T of static
   * @param mixed ...$arguments
   * @return T
   */
  public static function baseDispatch(...$arguments): static {
    $job = new static(...$arguments);

    $queueEnabled = CsPluginConfig::get('ext.queueEnabled');
    if (!$queueEnabled) {
      $job->handle();
      return $job;
    }

    // $queue = new JobQueue(GlobalVariables::$registry);
    // $queue->addJob(static::class, $arguments);
    return $job;
  }

  abstract public function handle(): void;
}
