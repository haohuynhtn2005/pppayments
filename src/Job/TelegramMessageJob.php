<?php
namespace Dell\WpShieldp\Job;

use ShieldPpPayment\Library\Job;
use ShieldPpPayment\Service\Telegram\Dto\TelegramMessageOptions;
use ShieldPpPayment\Service\Telegram\SendTelegramMessageService;
use ShieldPpPayment\Service\Telegram\TelegramTarget;

class TelegramMessageJob extends Job {
  public function __construct(
    private string $message,
    private ?TelegramMessageOptions $options = null,
    private ?TelegramTarget $target = null,
  ) {}

  public static function dispatch(
    string $message,
    ?TelegramMessageOptions $options = null,
    ?TelegramTarget $target = null,
  ) {
    parent::baseDispatch(...\func_get_args());
  }

  public function handle(): void {
    $sendTelegramMsgSrv = new SendTelegramMessageService($this->target);
    $sendTelegramMsgSrv->send($this->message, $this->options);
  }
}
