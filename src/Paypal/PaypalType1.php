<?php
namespace Dell\WpShieldpp\Paypal;

### PAYPAL API LIBRARY
### API v1
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;

class PaypalType1
{
  public function __construct(
    private $path,
    private $testmode,
    private $clientId,
    private $Secret,
  ) {
  }

  public function createType1Order($order, $cs_ref_code)
  {
    $order_id = $order->get_id();
    $orderNo = $order_id;
    $apiContext = new \PayPal\Rest\ApiContext(
      new \PayPal\Auth\OAuthTokenCredential(
        $this->clientId,
        $this->Secret
      )
    );
    $mode = $this->testmode ? 'LIVE' : 'sandbox';
    $apiContext->setConfig(
      array(
        'mode' => $mode
      )
    );

    //telegram_push_log('$apiContext'. print_r($apiContext, true));

    $payer = new Payer();
    $payer->setPaymentMethod("paypal");

    $amount = new Amount();
    $total = $order->get_total();
    $amount->setCurrency("USD")->setTotal($total);

    $transaction = new Transaction();
    $homeUrl = get_option('siteurl');

    $transaction->setAmount($amount)->setDescription(
      $this->convertSiteUrlToSiteName($homeUrl)
      . " Invoice #" . $orderNo
    )->setInvoiceNumber($orderNo);

    $redirectUrls = new RedirectUrls();
    $cancel_url = $homeUrl . '/wc-api/wc_cancel/';

    if (empty($this->path)) {
      $this->path = $homeUrl . '/wc-api/wc_return_url?orderId=' . $order_id . '&csRefCode=' . $cs_ref_code;
    }
    $redirectUrls->setReturnUrl($this->path)->setCancelUrl($cancel_url);

    $payment = new Payment();
    $payment->setIntent("sale")->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array($transaction));
    try {
      $payment->create($apiContext);
      $redirect_url = $payment->getApprovalLink();
    } catch (\PayPal\Exception\PayPalConnectionException $ex) {
      throw $ex;
    }

    try {
      update_post_meta($orderNo, 'cs_pp_payment_link', $redirect_url);
      $res = array(
        'status' => true,
        'payment_link' => $redirect_url,
        'qr_code' => '',
      );
      return $res;
    } catch (\PayPal\Exception\PayPalConnectionException $e) {
      throw $e;
    }
  }

  private function convertSiteUrlToSiteName(
    $homeUrl
  ) {
    $parsedUrl = parse_url($homeUrl);
    $host = $parsedUrl['host'] ?? '';
    $name = '';
    if (preg_match('/(.*?)\.(.*?)/', $host, $m)) {
      $name = ucfirst($m[1]);
    }

    return $name;
  }
}