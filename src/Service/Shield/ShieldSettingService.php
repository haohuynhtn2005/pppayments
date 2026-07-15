<?php
namespace ShieldPpPayment\Service\Shield;

use CS_STRIPE;

class ShieldSettingService
{
  public function processShieldSettingData(array $settings, array $data)
  {
    $paypalType = $data['paypal']['settings']['paypal_type'] ?? '';

    $paypalVersion = 'v2';
    switch ($paypalType) {
      case '1':
        $paypalVersion = 'v1';
        break;
      case '0':
        $paypalVersion = 'web';
        break;
    }

    $processedSettings = [
      'debug' => 'yes',
      'get_version' => [$paypalVersion],
      'business' => $data['paypal']['settings']['paypal_business_email'] ?? '',
      'API' => $data['paypal']['settings']['paypal_client_id'] ?? '',
      'Secret' => $data['paypal']['settings']['paypal_secret'] ?? '',
      'SOAP_API' => $data['paypal']['settings']['api_soap_username'] ?? '',
      'SOAP_PASS' => $data['paypal']['settings']['api_soap_password'] ?? '',
      'SOAP_Signature' => $data['paypal']['settings']['api_soap_signature'] ?? '',
      'testmode' => empty($data['paypal']['is_sandbox']) ? 'no' : 'yes',

      // 'proxy_type' => $data['proxy_type'] ?? '',
      // 'proxy_info' => $data['proxy_info'] ?? '',
      // 'proxy_host'        => $data['proxy_host'] ?? '',
      // 'proxy_port'        => $data['proxy_port'] ?? '',
      // 'proxy_auth_type'   => $data['proxy_auth_type'] ?? '',
      // 'proxy_username'    => $data['proxy_username'] ?? '',
      // 'proxy_password'    => $data['proxy_password'] ?? '',
    ];

    foreach ($processedSettings as $key => $val) {
      if (\in_array($key, ['get_version'])) {
        $value = $val;
      } else {
        $value = sanitize_text_field($val);
      }
      $processedSettings[$key] = $value;
    }

    $mergedSettings = array_merge($settings, $processedSettings);
    return $mergedSettings;
  }

  public function updateStripeSetting($data)
  {
    return;
    $data_stripes = array(
      'title' =>
        $data['stripes']['settings']['title'] ?? 'Stripe Payment',
      'enabled' =>
        isset($data['stripes']['active']) && $data['stripes']['active']
        ? 'yes' : 'no',
      'client_id' =>
        $data['stripes']['settings']['stripes_client_id'] ?? '',
      'secret' =>
        $data['stripes']['settings']['stripes_secret'] ?? '',
      'endpoint_secret' =>
        $data['stripes']['settings']['stripes_endpoint_secret'] ?? '',
      'type' =>
        $data['stripes']['settings']['stripes_type'] ?? '',
      'testmode' =>
        isset($data['stripes']['is_sandbox']) && $data['stripes']['is_sandbox'] ? 'yes' : 'no',
    );

    $allowed_settings = array(
      'title' => '',
      'enabled' => 'no',
      'client_id' => '',
      'secret' => '',
      'endpoint_secret' => '',
      'type' => '',
      'testmode' => '',
    );
    $cs_stripe = new CS_STRIPE();
    foreach ($allowed_settings as $key => $default_value) {
      $value = isset($data_stripes[$key]) ? sanitize_text_field($data_stripes[$key])
        : $default_value;
      $cs_stripe->update_option($key, $value);
    }
  }
}