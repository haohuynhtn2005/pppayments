<?php
namespace Dell\WpShieldpp\Paypal;

class PaypalType0
{

  public function __construct(
    private $clientId,
    private $Secret,
    private $testmode
  ) {
  }

  public function createType0Order()
  {
    $OAuthTokenCredential = new \PayPal\Auth\OAuthTokenCredential(
      $this->clientId,
      $this->Secret
    );

    $apiContext = new \PayPal\Rest\ApiContext($OAuthTokenCredential);

    if ('yes' == $this->testmode) {
      $apiPaypalConfig = ['mode' => 'sandbox'];
    } else {
      $apiPaypalConfig = ['mode' => 'live'];
    }

    $apiContext->setConfig($apiPaypalConfig);

    try {
      $webhook = new \PayPal\Api\Webhook();
      $webhookList = $webhook->getAllWithParams([], $apiContext);

      $webhookExists = false;
      $ipnLink = WC()->api_request_url('wc_shieldpp_ipn');
      telegram_push_log($ipnLink);

      foreach ($webhookList->getWebhooks() as $existingWebhook) {
        if ($existingWebhook->getUrl() === $ipnLink) {
          $webhookExists = true;
          break;
        }
      }

      if (!$webhookExists) {
        $webhook = new \PayPal\Api\Webhook();
        $webhook->setUrl($ipnLink);
        telegram_push_log($webhook);

        $webhookEventTypes = array();
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType('{"name":"INVOICING.INVOICE.PAID"}');
        $webhook->setEventTypes($webhookEventTypes);

        $webhook->create($apiContext);
        //echo "Webhook created successfully!";
      } else {
        //echo "Webhook already exists.";
      }

      return;
    } catch (\Exception $e) {
      $message = "Error:\n";
      $message .= "Message: Error in create_paypal_invoice paypal_type = 0 cannot create Webhook Link " . $e->getMessage() . "\n";
      $message .= "File: " . $e->getFile() . "\n";
      $message .= "Line: " . $e->getLine() . "\n";

      plugin_custom_log($message, 'debug.log');
      telegram_push_log($message);

      $res = array(
        'status' => false,
        'msg' => $e->getMessage()
      );
      return $res;
    }

    $orderNo = $order_id;
    $business_name = get_bloginfo('name');
    $business_email = $paypal_settings['paypal_business_email'] ?? '';

    $total = $order->get_total();
    $invoice = new Invoice();

    $invoice->setMerchantInfo(new MerchantInfo())
      ->setBillingInfo(array(new BillingInfo()))
      ->setShippingInfo(new ShippingInfo())
      ->setReference($orderNo);

    $invoice->getMerchantInfo()->setEmail($business_email)->setBusinessName($business_name);

    $billing = $invoice->getBillingInfo();
    $billing[0]->setEmail($order->get_billing_email());

    $items = array();
    $items[0] = new InvoiceItem();
    $items[0]->setName("Pay Invoice #" . $orderNo)->setQuantity(1)->setUnitPrice(new Currency());

    $items[0]->getUnitPrice()->setCurrency("USD")->setValue($total);
    $invoice->setItems($items)
      ->setPaymentTerm(array('due_date' => date("Y-m-d T", strtotime("+63 hours"))))
      ->setTerms("NO REFUND, Please contact our team to request TRIAL");

    $request = clone $invoice;
    try {

      $invoice->create($apiContext);
      if ('yes' == $this->testmode) {
        $paylink = "https://www.sandbox.paypal.com/invoice/p/#"
          . str_replace("-", "", str_replace("INV2-", "", $invoice->getId()));
      } else {
        $paylink = "https://www.paypal.com/invoice/p/#"
          . str_replace("-", "", str_replace("INV2-", "", $invoice->getId()));
      }

      $invoice = Invoice::get($invoice->getId(), $apiContext);

      $qr = Invoice::qrCode($invoice->getId(), array('height' => '300', 'width' => '300'), $apiContext);

      $sendStatus = $invoice->send($apiContext);

      update_post_meta($orderNo, 'cs_pp_qr_code', $qr->getImage());
      update_post_meta($orderNo, 'cs_pp_payment_link', $paylink);

      $res = array(
        'status' => true,
        'payment_link' => $paylink,
        'qr_code' => $qr->getImage(),
        'cs_ref_code' => $cs_ref_code,
        'invoice_id' => $invoice->getId()
      );

      return $res;
    } catch (\Exception $e) {
      $message = "Error:\n";
      $message .=
        "Message: Error in create_paypal_invoice paypal_type = 0 cannot create Payment link "
        . $e->getMessage() . "\n";
      $message .= "File: " . $e->getFile() . "\n";
      $message .= "Line: " . $e->getLine() . "\n";

      plugin_custom_log($message, 'debug.log');
      telegram_push_log($message);

      $res = array(
        'status' => false,
        'msg' => $e->getMessage()
      );

      return $res;
    }
  }
}