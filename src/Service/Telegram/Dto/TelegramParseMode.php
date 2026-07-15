<?php
namespace ShieldPpPayment\Service\Telegram\Dto;

enum TelegramParseMode: string {
  case HTML = 'HTML';
  case MARKDOWN = 'Markdown';
  case MARKDOWN_V2 = 'MarkdownV2';
}
