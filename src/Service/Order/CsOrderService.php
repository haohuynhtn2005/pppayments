<?php
namespace ShieldPpPayment\Service\Order;

use Automattic\WooCommerce\Enums\ProductTaxStatus;
use ShieldPpPayment\Service\Order\ProductService;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesGetRequest;

use ShieldPpPayment\Helper\ClientIpHelper;
use ShieldPpPayment\Paypal\PaypalClient;
use ShieldPpPayment\Paypal\PpaymentsPluginConfigDto;
use ShieldPpPayment\Paypal\Nvp\PaypalNvpService;
use ShieldPpPayment\Service\Order\PostService;
use ShieldPpPayment\Service\Shield\ShieldWebHookService;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Product_Variation;

class CsOrderService
{

  public function isValidClient($order)
  {
    $orderId      = $order->get_id();
    $storedId     = get_post_meta($orderId, '_customer_ip_address', true);
    $computedSign = IpSignService::genIpSign($storedId, $orderId);
    $clientIp     = ClientIpHelper::getClientIp();
    $currentSign  = IpSignService::genIpSign($clientIp, $orderId);
    return hash_equals($computedSign, $currentSign);
  }

  public function createOrder(
    $cs_ref_code,
    float $targetPrice,
    $customer_email,
    $product_name,
    $first_name,
    $last_name,
    $mc_success_url,
    $mc_failed_url,
    $customer_ip
  ) {
    $address = [
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $customer_email
    ];

    $order = wc_create_order();
    $order->set_customer_ip_address($customer_ip);
    $order->set_address($address, 'billing');
    $order->add_order_note("Order process via API CS System");
    $this->processOrder($order, $targetPrice, $product_name);
    $order_id = $order->get_id();

    WC()->session->set('order_awaiting_payment', $order_id);
    update_post_meta($order_id, 'cs_ref_code', $cs_ref_code);
    update_post_meta($order_id, 'mc_success_url', $mc_success_url);
    update_post_meta($order_id, 'mc_failed_url', $mc_failed_url);
    if ($customer_ip) {
      update_post_meta($order_id, '_customer_ip_address', sanitize_text_field($customer_ip));
    }

    return $order;
  }

  private function processOrder(
    WC_Order $order,
    float $targetPrice,
    $product_name
  ) {
    $this->addOrderProductAndFee($order, $targetPrice, $product_name);
    /** @var \WC_Payment_Gateways $payment_gateways */
    $payment_gateways = WC()->payment_gateways;
    /** @var \WC_Payment_Gateway[] $gateways */
    $gateways = $payment_gateways->payment_gateways();
    $order->set_payment_method($gateways['pppayments'] ?? '');
    $order->calculate_taxes();
    $order->calculate_totals();
    $order->save();
  }

  private function addOrderProductAndFee(
    WC_Order $order,
    float $targetPrice,
    $product_name
  ) {
    $feeAmount      = 0;
    $discountAmount = 0;
    if (false) {
      $product = $this->getProductForOrder($targetPrice);
    }
    $productSrv = new ProductService();
    $product    = $productSrv->find_closest_product_by_price($targetPrice);
    // $order->set_total($targetPrice);
    $order->set_currency('USD');
    if (!$product) {
      return;
    }

    if ($product instanceof WC_Product_Variation) {
      $parentName = $product->get_title();
      $product->set_name($parentName);
    }
    /**
     * 
     * @since 1.3.3
     * @deprecated These data does not affect anything
     */
    $cart_item_data = [
      'custom_price' => $targetPrice,
      'custom_product_name' => $product_name,
    ];
    // $product = $this->getProductForOrder($targetPrice);
    // // $product->set_price($product_price);
    // $productPrice = $product;
    // $order->add_product($product, 1, $cart_item_data);
    // $order->set_total($targetPrice);

    // // add Gateway
    // $payment_gateways = WC()->payment_gateways->payment_gateways();
    // $order->set_payment_method($payment_gateways['pppayments'] ?? '');
    // $order->calculate_totals();
    $order->add_product($product, 1, $cart_item_data);

    $productPrice = $productSrv->getProductPrice($product);
    $diff         = abs($targetPrice - $productPrice);
    if ($productPrice > $targetPrice) {
      $discountAmount = $diff;
    } else if ($targetPrice > $productPrice) {
      $feeAmount = $diff;
    }
    if ($discountAmount) {
      $coupon = new WC_Coupon();
      // $coupon->set_code('SUMMER10');
      // $coupon->set_discount_type('fixed_cart'); // fixed_cart, percent, fixed_product
      // $coupon->set_amount($discountAmount);
      // $coupon->set_individual_use(false);
      // $coupon->set_usage_limit(0);
      // $coupon->set_usage_limit_per_user(0);
      // $coupon->set_free_shipping(false);
      // $coupon->set_date_expires(null);
      // $coupon->set_virtual(true);
      // $coupon->save();
      // $error = $order->apply_coupon($coupon);


      $items = $order->get_items();
      // $item = reset($items);
      /** @var WC_Order_Item_Product $item */
      $item = current($items);
      if ($item instanceof WC_Order_Item_Product) {
        $item->set_total($targetPrice);
      }

    }

    if ($feeAmount) {
      $fee = new WC_Order_Item_Fee();
      $fee->set_name('Handling fee');
      $fee->set_amount($feeAmount);
      // Total after tax adjustments
      $fee->set_total($feeAmount);
      $fee->set_tax_status(ProductTaxStatus::NONE);
      $order->add_item($fee);
    }
  }

  private function getProductForOrder(float $targetPrice)
  {
    $args       = ['post_type' => 'product', 'posts_per_page' => -1];
    $products   = get_posts($args);
    $product_id = $products[array_rand($products)]->ID;
    if (!$product_id) {
      $product_id = 7015;
    }

    $product    = wc_get_product($product_id);
    $variations = $product->get_children();
    // $variations = [];
    if ($variations) {
      $variationId = $variations[array_rand($variations)] ?? null;
      $product     = wc_get_product($variationId);
    }
    return $product;
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
    // $payee        = $result->purchase_units[0]->payee ?? [];
    // $payee_email  = $payee->email_address ?? '';
    $payee_email  = $result->purchase_units[0]->payee->email_address ?? '';
    $buyer_email  = $result->payer->email_address ?? '';
    $buyer_status = $result->payer->status ?? '';

    $invoiceId   = $result->purchase_units[0]->payments->captures[0]->invoice_id ?? '';
    $orderstatus = $result->purchase_units[0]->payments->captures[0]->status ?? '';
    $txn_id      = $result->purchase_units[0]->payments->captures[0]->id ?? '';
    $referenceId = $result->purchase_units[0]->reference_id ?? '';

    $captureStatus = $this->getCaptureStatus($txn_id, $pluginConfig);
    if (strtolower($buyer_status) == "verified") {
      $captureStatus = "Completed";
    }

    $postSrv = new PostService();
    $postId  = $postSrv->getPostIdViaPaypalInvoiceId($invoiceId);
    $orderId = (int) $postId;
    $order   = wc_get_order($orderId);
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
    $this->updateOrderInfo($order, $result);
    $order->save();

    $csRefCode        = get_post_meta($orderId, 'cs_ref_code', true);
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

  public function updateOrderInfo(WC_Order $order, $result)
  {
    // $line1         = $address?->getAddressLine1();
    // $line2         = $address?->getAddressLine2();
    // $zone          = $address?->getAdminArea1();
    // $city          = $address?->getAdminArea2();
    // $postalCode    = $address?->getPostalCode();
    // $countryCode   = $address?->getCountryCode();

    // $payerName    = $payer?->getName();
    // $givenName    = $payerName?->getGivenName();
    // $payerSurname = $payerName?->getSurname();

    $paymentSource = $result->payment_source ?? null;
    $paypalSource  = $paymentSource->paypal ?? null;
    $address       = $paypalSource->address ?? null;

    $line1       = $address->address_line_1 ?? null;
    $line2       = $address->address_line_2 ?? null;
    $zone        = $address->admin_area_1 ?? null;
    $city        = $address->admin_area_2 ?? null;
    $postalCode  = $address->postal_code ?? null;
    $countryCode = $address->country_code ?? null;

    $payerEmail     = $paypalSource->email_address ?? null;
    $payerName      = $paypalSource->name ?? null;
    $givenName      = $payerName->given_name ?? null;
    $surname        = $payerName->surname ?? null;
    $phoneNumber    = $paypalSource->phone_number ?? null;
    $nationalNumber = $phoneNumber->national_number ?? null;
    if (!$nationalNumber) {
      // $nationalNumber = '09' . rand(10000000, 99999999);
    }
    $setIfString = static function ($value, callable $setter): void {
      if (\is_string($value) && $value !== '') {
        $setter($value);
      }
    };

    $setIfString($givenName, fn($v) => $order->set_billing_first_name($v));
    $setIfString($surname, fn($v) => $order->set_billing_last_name($v));
    $setIfString($payerEmail, fn($v) => $order->set_billing_email($v));
    $setIfString($line1, fn($v) => $order->set_billing_address_1($v));
    $setIfString($line2, fn($v) => $order->set_billing_address_2($v));
    $setIfString($city, fn($v) => $order->set_billing_city($v));
    $setIfString($zone, fn($v) => $order->set_billing_state($v));
    $setIfString($postalCode, fn($v) => $order->set_billing_postcode($v));
    $setIfString($countryCode, fn($v) => $order->set_billing_country($v));
    $setIfString($nationalNumber, fn($v) => $order->set_billing_phone($v));

    // $order->set_billing_first_name($givenName);
    // $order->set_billing_last_name($surname);
    // $order->set_billing_email($payerEmail);

    // $order->set_billing_address_1($line1);
    // $order->set_billing_address_2($line2);
    // $order->set_billing_city($city);
    // $order->set_billing_state($zone);
    // $order->set_billing_postcode($postalCode);
    // $order->set_billing_country($countryCode);
    // $order->set_billing_phone($nationalNumber);
  }

  private function getCaptureStatus(
    $txn_id,
    PpaymentsPluginConfigDto $pluginConfig,
  ) {
    $client       = $this->get_paypal_client_type_2($pluginConfig);
    $paypalNvpSrv =
      new PaypalNvpService(
        'yes' == $pluginConfig->testMode,
        $pluginConfig->soapApi,
        $pluginConfig->soapPass,
        $pluginConfig->soapSignature
      );
    // $paypalNvpSrv->get_nvp_payment_status($txn_id);

    $request       = new CapturesGetRequest($txn_id);
    $captureRes    = $client->execute($request);
    $captureInfo   = $captureRes->result;
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
