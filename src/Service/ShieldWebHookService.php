<?php
namespace Dell\WpShieldpp\Service;

use Exception;
use Shield_API;


class ShieldWebHookService
{
  public function sendWebhook(array $data = []): never
  {
    $defaults = [
      'order_id' => null,
      'paymentStatus' => '',
      'transaction_id' => '',
      'cs_ref_code' => '',
      'invoice_id' => '',
      'payer_email' => '',
      'buyer_email' => ''
    ];

    $data = array_merge($defaults, $data);

    $order_id = $data['order_id'];
    $paymentStatus = $data['paymentStatus'];
    $transaction_id = $data['transaction_id'];
    $cs_ref_code = $data['cs_ref_code'];
    $invoice_id = $data['invoice_id'];
    $payer_email = $data['payer_email'];
    $buyer_email = $data['buyer_email'];

    $message = "Received IPN request\n";

    try {
      $apiUrl = get_option('cs_api_url');
      $merchantKey = get_option('cs_merchant_key');
      $shieldKey = get_option('cs_shield_key');
      $shield_api = new Shield_API(
        $apiUrl,
        $merchantKey,
        $shieldKey
      );

      if ($cs_ref_code) {
        // come from cash shield, post back to api
        $callback_post = array(
          'type' => 1,
          'cs_ref_code' => $cs_ref_code,
          //transaction from pp or stripe return
          'transaction_id' => $transaction_id,
          'callback_data' => json_encode($data)
        );

        $webhook_res = $shield_api->webhookIPN($callback_post);
        $message .= "Shield API response: " . json_encode($webhook_res);
      } else {
        //invoice webhoook
        $callback_post = array(
          'type' => 0,
          'invoice_id' => $invoice_id,
          //transaction from pp or stripe return
          'transaction_id' => $transaction_id,
          'callback_data' => json_encode($data)
        );

        $webhook_res = $shield_api->webhookIPN($callback_post);
        $message .= "Shield API response: " . json_encode($webhook_res);
      }
      telegram_push_log("callback_post: " . print_r($message, true));
    } catch (Exception $e) {
      $message = "Error:\n";
      $message .= "Message: Error in webhook paypal_type = 1" . $e->getMessage() . "\n";
      $message .= "File: " . $e->getFile() . "\n";
      $message .= "Line: " . $e->getLine() . "\n";

      plugin_custom_log($message, 'debug.log');
      telegram_push_log($message);
    }
    die();
  }
}