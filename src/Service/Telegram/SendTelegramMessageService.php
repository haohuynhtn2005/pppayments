<?php
namespace ShieldPpPayment\Service\Telegram;

use ShieldPpPayment\Library\Logger;
use ShieldPpPayment\Service\Telegram\Dto\TelegramMessageOptions;

class SendTelegramMessageService {
  private TelegramTarget $target;

  public function __construct(?TelegramTarget $target = null) {
    $this->target = $target ?? new TelegramTarget();
  }

  public function send(
    string $message,
    ?TelegramMessageOptions $options = null,
  ) {
    $options ??= new TelegramMessageOptions();
    $target = $this->target;
    $url = "https://api.telegram.org/bot{$target->botToken}/sendMessage";

    $payload = [
      'parse_mode' => $options->parseMode?->value,
      'chat_id' => $target->chatId,
      'message_thread_id' => $target->threadId,
      'text' => $message,
      'disable_web_page_preview' => $options->disableWebPagePreview,
      'disable_notification' => $options->disableNotification,
      'reply_to_message_id' => $options->replyToMessageId,
      'reply_markup' => $options->replyMarkup
        ? json_encode($options->replyMarkup)
        : null,
    ];
    $payload = array_filter($payload, fn($v) => $v !== null);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = null;
    if ($response === false) {
      $err = curl_error($ch);
      $logMsg = <<<MSG
      *Send Telegram msg failed*
      $err
      $message
      MSG;
      Logger::warning($logMsg);
    }
    return [$response, $httpCode, $err];
  }
}
