<?php
namespace Dell\WpShieldpp\Service;

use Throwable;

class ErrorHandler
{
  public static function handleGeneralError(Throwable $th)
  {
    $siteUrl = home_url();
    $domain = preg_replace('#^https?://#', '', $siteUrl);
    $throwableClass = $th::class;
    $message = <<<TEXT
    Error!
    $throwableClass: {$th->getMessage()} in
    {$th->getFile()}:{$th->getLine()}
    Stack Trace:
    {$th->getTraceAsString()}
    TEXT;
    plugin_custom_log($message);

    $telegramMsg = <<<TEXT
    $throwableClass: {$th->getMessage()} in
    {$th->getFile()}:{$th->getLine()}
    TEXT;
    telegram_push_log($telegramMsg);
  }
}