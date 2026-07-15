<?php
namespace ShieldPpPayment\Service\Telegram;

use ShieldPpPayment\Library\CsPluginConfig;

final class TelegramTarget {
  public readonly string $threadId;
  public readonly string $botToken;
  public readonly string $chatId;

  public function __construct(
    ?string $threadId = null,
    ?string $chatId = null,
    ?string $botToken = null,
  ) {
    $this->threadId =
      $threadId ?? CsPluginConfig::get('custom.telegram.default.threadId');
    $this->chatId =
      $chatId ?? CsPluginConfig::get('custom.telegram.default.chatId');
    $this->botToken =
      $botToken ?? CsPluginConfig::get('custom.telegram.default.botToken');
  }
}
