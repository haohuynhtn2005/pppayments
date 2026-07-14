<?php
namespace Dell\WpShieldpp\Service;

class ShieldWebHookService
{
  public function sendWebhook(array $data = [])
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

    $transaction_id = $data['transaction_id'];
    $cs_ref_code = $data['cs_ref_code'];
    $invoice_id = $data['invoice_id'];

    $callback_post = [];
    if ($cs_ref_code) {
      // come from cash shield, post back to api
      $callback_post = [
        'type' => 1,
        'cs_ref_code' => $cs_ref_code,
        //transaction from pp or stripe return
        'transaction_id' => $transaction_id,
        'callback_data' => json_encode($data)
      ];
    } else {
      //invoice webhoook
      $callback_post = [
        'type' => 0,
        'invoice_id' => $invoice_id,
        //transaction from pp or stripe return
        'transaction_id' => $transaction_id,
        'callback_data' => json_encode($data)
      ];

    }

    $apiUrl = get_option('cs_api_url');
    $merchantKey = get_option('cs_merchant_key');
    $shieldKey = get_option('cs_shield_key');
    $shieldApiSrv = new ShieldApiService(
      $apiUrl,
      $merchantKey,
      $shieldKey
    );
    $webhook_res = $shieldApiSrv->sendIpnWebhook($callback_post);
  }
}