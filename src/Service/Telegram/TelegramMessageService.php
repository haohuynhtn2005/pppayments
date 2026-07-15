<?php
namespace ShieldPpPayment\Service\Telegram;

use Dell\WpShieldp\Job\TelegramMessageJob;
use ShieldPpPayment\Library\CsPluginConfig;
use ShieldPpPayment\Service\Telegram\Dto\TelegramMessageOptions;
use ShieldPpPayment\Service\Telegram\Dto\TelegramParseMode;
use ShieldPpPayment\Service\Telegram\Helper\TelegramMessageHelper;

class TelegramMessageService {
  public static function sendBasicMessage1(
    $msg,
    $json = null,
    ?TelegramTarget $target = null,
  ) {
    $link = TelegramMessageHelper::getBeautifulLink();
    $escapedLink = TelegramMessageHelper::escapeMarkdownV2($link);
    $dateTime = gmdate('Y-m-d H:i:s');
    $escapedDateTime = TelegramMessageHelper::escapeMarkdownV2($dateTime);
    $escapedMsg = TelegramMessageHelper::escapeMarkdownV2($msg);
    $msgPart = $msg ? "{$escapedMsg}\n" : '';
    $body = json_encode($json);
    $escapedBody = TelegramMessageHelper::escapeMarkdownV2($body, 'code');
    $bodyPart = $json ? "```json\n{$escapedBody}\n```\n" : '';

    $telegramErrorMsg = <<<MD
    \[{$escapedDateTime}\]:
    *{$escapedLink}*
    {$msgPart}{$bodyPart}
    MD;
    $telegramOpt = new TelegramMessageOptions(TelegramParseMode::MARKDOWN_V2);
    TelegramMessageJob::dispatch($telegramErrorMsg, $telegramOpt, $target);
  }

  public static function sendBasicMessageWebhook($msg, $json = null) {
    $target = new TelegramTarget(
      CsPluginConfig::get('custom.telegram.webhook.threadId'),
    );
    self::sendBasicMessage($msg, $json, $target);
  }

  public static function sendBasicMessageRecovery($msg, $json = null) {
    $target = new TelegramTarget(
      CsPluginConfig::get('custom.telegram.recovery.threadId'),
    );
    self::sendBasicMessage($msg, $json, $target);
  }

  public static function sendBasicMessage(
    $msg,
    $json = null,
    ?TelegramTarget $target = null,
  ) {
    $link = TelegramMessageHelper::getBeautifulLink();
    $escapedLink = TelegramMessageHelper::escapeMarkdownV2($link);
    $dateTime = gmdate('Y-m-d H:i:s');
    $escapedDateTime = TelegramMessageHelper::escapeMarkdownV2($dateTime);
    $escapedMsg = TelegramMessageHelper::escapeMarkdownV2($msg);

    $header = <<<MD
    \\[{$escapedDateTime}\\]:
    *{$escapedLink}*
    MD;
    $msgPart = $msg ? "\n{$escapedMsg}\n" : '';
    $telegramOpt = new TelegramMessageOptions(TelegramParseMode::MARKDOWN_V2);
    // Telegram hard limit ~= 4096 chars, keep some buffer
    $maxLength = 3800;

    // No JSON => simple split whole text if needed
    if ($json === null) {
      $fullMessage = $header . $msgPart;
      foreach (self::splitTelegramMessage($fullMessage, $maxLength) as $chunk) {
        TelegramMessageJob::dispatch($chunk, $telegramOpt, $target);
      }
      return;
    }

    $body = json_encode(
      $json,
      JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    $escapedBody = TelegramMessageHelper::escapeMarkdownV2($body, 'code');

    $prefix = $header . $msgPart;
    $codePrefix = "```json\n";
    $codeSuffix = "\n```\n";

    $availableForJson =
      $maxLength -
      mb_strlen($prefix) -
      mb_strlen($codePrefix) -
      mb_strlen($codeSuffix);

    // If header itself is already too long, split it first
    if ($availableForJson < 200) {
      foreach (self::splitTelegramMessage($prefix, $maxLength) as $chunk) {
        TelegramMessageJob::dispatch($chunk, $telegramOpt, $target);
      }
      $prefix = '';
      $availableForJson =
        $maxLength - mb_strlen($codePrefix) - mb_strlen($codeSuffix);
    }

    $jsonChunks = self::splitByLength($escapedBody, $availableForJson);
    foreach ($jsonChunks as $index => $jsonChunk) {
      $message = '';
      if ($index === 0 && $prefix !== '') {
        $message .= $prefix;
      }

      $message .= $codePrefix . $jsonChunk . $codeSuffix;
      TelegramMessageJob::dispatch($message, $telegramOpt, $target);
    }
  }

  private static function splitTelegramMessage(
    string $text,
    int $maxLength = 3800,
  ): array {
    return self::splitByLength($text, $maxLength);
  }

  private static function splitByLength(string $text, int $maxLength): array {
    $chunks = [];
    $length = mb_strlen($text);
    $offset = 0;

    while ($offset < $length) {
      $chunk = mb_substr($text, $offset, $maxLength);
      if ($offset + $maxLength < $length) {
        $lastNewline = mb_strrpos($chunk, "\n");
        if ($lastNewline !== false && $lastNewline > 0) {
          $chunk = mb_substr($chunk, 0, $lastNewline);
        }
      }

      if ($chunk === '') {
        $chunk = mb_substr($text, $offset, $maxLength);
      }

      $chunks[] = $chunk;
      $offset += mb_strlen($chunk);
    }

    return $chunks;
  }
}
