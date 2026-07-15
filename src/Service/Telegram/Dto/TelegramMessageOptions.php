<?php
namespace ShieldPpPayment\Service\Telegram\Dto;

final class TelegramMessageOptions {
  public function __construct(
    public readonly ?TelegramParseMode $parseMode = null,
    public readonly bool $disableWebPagePreview = false,
    public readonly bool $disableNotification = false,
    public readonly ?int $replyToMessageId = null,
    public readonly ?array $replyMarkup = null,
  ) {}
}
