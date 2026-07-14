<?php
namespace Dell\WpShieldpp\Service;

use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesGetRequest;

use Dell\WpShieldpp\Helper\ClientIpHelper;
use Dell\WpShieldpp\Paypal\PaypalClient;
use Dell\WpShieldpp\Paypal\PaypalNvpService;
use Dell\WpShieldpp\Paypal\ProxyPayPalHttpClient;
use Dell\WpShieldpp\Paypal\ProxyConfigDto;
use Dell\WpShieldpp\Paypal\PpaymentsPluginConfigDto;
use Dell\WpShieldpp\Service\PostService;
use Dell\WpShieldpp\Service\ShieldWebHookService;

class CsOrderService
{

  public function isValidClient($order)
  {
    $orderId = $order->get_id();
    $storedId = get_post_meta($orderId, '_customer_ip_address', true);
    $computedSign = IpSignService::genIpSign($storedId, $orderId);
    $clientIp = ClientIpHelper::getClientIp();
    $currentSign = IpSignService::genIpSign($clientIp, $orderId);
    return hash_equals($computedSign, $currentSign);
  }

  public function createOrder(
    $cs_ref_code,
    $product_price,
    $customer_email,
    $product_name,
    $first_name,
    $last_name,
    $mc_success_url,
    $mc_failed_url,
    $customer_ip
  ) {
    $cart_item_data = [
      'custom_price' => $product_price,
      'custom_product_name' => $product_name
    ];
    $address = [
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $customer_email
    ];

    $order = wc_create_order();
    $order->set_customer_ip_address($customer_ip);
    $order->set_address($address, 'billing');
    $order->add_order_note("Order process via API CS System");

    $args = array('post_type' => 'product', 'posts_per_page' => -1);
    $products = get_posts($args);
    $product_id = $products[array_rand($products)]->ID;
    if (!$product_id) {
      $product_id = 7015;
    }

    $product = wc_get_product($product_id);
    $variations = $product->get_children();
    // $variations = [];
    if ($variations) {
      $variationId = $variations[array_rand($variations)] ?? null;
      $product = wc_get_product($variationId);
    }

    $product->set_price($product_price);
    $order->add_product($product, 1, $cart_item_data);
    $order->set_total($product_price);
    $randomPhoneNumber = '09' . rand(10000000, 99999999);
    $order->set_billing_phone($randomPhoneNumber);

    // add Gateway
    $payment_gateways = WC()->payment_gateways->payment_gateways();
    $order->set_payment_method($payment_gateways['pppayments']);

    $order->calculate_totals();
    WC()->session->order_awaiting_payment = $order->get_id();

    $order_id = $order->get_id();
    update_post_meta($order_id, 'cs_ref_code', $cs_ref_code);
    update_post_meta($order_id, 'mc_success_url', $mc_success_url);
    update_post_meta($order_id, 'mc_failed_url', $mc_failed_url);
    if ($customer_ip) {
      update_post_meta($order_id, '_customer_ip_address', sanitize_text_field($customer_ip));
    }

    return $order;
  }

  public function capturePayment(
    $token,
    PpaymentsPluginConfigDto $pluginConfig,
  ) {
    $client = $this->get_paypal_client_type_2($pluginConfig);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $request = new OrdersCaptureRequest($token);
    $request->prefer('return=representation');
    $response = $client->execute($request);

    $result = $response->result;
    $payee_email = $result->purchase_units[0]->payee->email_address ?? '';
    $buyer_email = $result->payer->email_address ?? '';
    $buyer_status = $result->payer->status ?? '';

    $invoiceId = $result->purchase_units[0]->payments->captures[0]->invoice_id ?? '';
    $orderstatus = $result->purchase_units[0]->payments->captures[0]->status ?? '';
    $txn_id = $result->purchase_units[0]->payments->captures[0]->id ?? '';
    $referenceId = $result->purchase_units[0]->reference_id ?? '';

    $captureStatus = $this->getCaptureStatus($txn_id, $pluginConfig);
    if (strtolower($buyer_status) == "verified") {
      $captureStatus = "Completed";
    }

    $postSrv = new PostService();
    $postId = $postSrv->getPostIdViaPaypalInvoiceId($invoiceId);
    $orderId = (int) $postId;
    $order = wc_get_order($orderId);
    if ($order->get_status() == "pending") {
      if ($captureStatus == "Completed") {
        $order->add_order_note(
          \sprintf(__('Payment success (Trans ID: %s)', 'wc-ppcp'), $txn_id)
        );
        $order->payment_complete($txn_id);
      } else if ($captureStatus == "Pending") {
        $order->add_order_note(
          \sprintf(__('Payment success (Trans ID: %s)', 'wc-ppcp'), $txn_id)
        );
        $order->update_status('on-hold', __('Processing', 'woocommerce'));
      } else {
        $order->add_order_note(
          \sprintf(__('Payment failed (Trans ID: %s)', 'wc-ppcp'), $txn_id)
        );
        $order->update_status('failed', __('Failed', 'woocommerce'));
      }
    }

    $order->update_meta_data('_paypal_transaction_id', $txn_id);
    $order->update_meta_data('_paypal_transaction_reference', $referenceId);
    $order->save();

    $csRefCode = get_post_meta($orderId, 'cs_ref_code', true);
    $shieldWebhookSrv = new ShieldWebHookService();
    $shieldWebhookSrv->sendWebhook([
      'order_id' => $orderId,
      'paymentStatus' => $captureStatus,
      'transaction_id' => $txn_id,
      'cs_ref_code' => $csRefCode,
      'invoice_id' => (int) $orderId,
      'payer_email' => $payee_email,
      'buyer_email' => $buyer_email
    ]);

    return $order;
  }

  private function getCaptureStatus(
    $txn_id,
    PpaymentsPluginConfigDto $pluginConfig,
  ) {
    $client = $this->get_paypal_client_type_2($pluginConfig);
    $paypalNvpSrv =
      new PaypalNvpService(
        'yes' == $pluginConfig->testMode,
        $pluginConfig->soapApi,
        $pluginConfig->soapPass,
        $pluginConfig->soapSignature
      );
    // $paypalNvpSrv->get_nvp_payment_status($txn_id);

    $request = new CapturesGetRequest($txn_id);
    $captureRes = $client->execute($request);
    $captureInfo = $captureRes->result;
    $captureStatus = $captureInfo->status ?? null;

    $map = [
      'COMPLETED' => 'Completed',
      'DECLINED' => 'Denied',
      'PARTIALLY_REFUNDED' => 'Partially-Refunded',
      'PENDING' => 'Pending',
      'REFUNDED' => 'Refunded',
      'FAILED' => 'Failed',
    ];
    return $map[$captureStatus] ?? 'None';
  }

  private function get_paypal_client_type_2(
    PpaymentsPluginConfigDto $pluginConfig,
  ) {
    $paypalCt = new PaypalClient();
    return $paypalCt->getPaypalClientV2($pluginConfig);
  }
}
