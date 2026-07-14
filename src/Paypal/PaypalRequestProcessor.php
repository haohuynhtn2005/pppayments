<?php
namespace Dell\WpShieldpp\Paypal;

class PaypalRequestProcessor
{
  public function __construct(
    // private bool $isTestMode,
    // private string $apiUser,
    // private string $apiPassword,
    // private string $apiSignature,
  ) {
  }

  public function processHeaders($headers)
  {
    $processedHeaders = $headers;
    $userAgent = $processedHeaders['user-agent'] ?? '';
    $contentType = $processedHeaders['Content-Type'] ?? '';
    $authorization = $processedHeaders['Authorization'] ?? '';
    unset($processedHeaders['asdfsdaf']);
    unset($processedHeaders['user-agent']);
    unset($processedHeaders['sdk_name']);
    unset($processedHeaders['sdk_name']);
    unset($processedHeaders['sdk_version']);
    unset($processedHeaders['sdk_tech_stack']);
    unset($processedHeaders['api_integration_type']);
    $newHeaders = [
      'Authorization' => $authorization,
      'Content-Type' => $contentType,
      ...$processedHeaders,
      'PayPal-Request-Id' => uniqid('ppcp-', \true),
      'PayPal-Client-Metadata-Id' => $this->session_id(),
      'PayPal-Partner-Attribution-Id' => 'WooPPCP_Ecom_PS_CoreProfiler',
    ];
    return $newHeaders;
  }

  private function session_id(): string
  {
    if (WC()->session === null) {
      return '';
    }
    $fraudnet_session_id = WC()->session->get('ppcp_fraudnet_session_id');
    if (is_string($fraudnet_session_id) && $fraudnet_session_id !== '') {
      return $fraudnet_session_id;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['pay_for_order']) && $_GET['pay_for_order'] === 'true') {
      // phpcs:ignore WordPress.Security.NonceVerification.Missing
      $pui_pay_for_order_session_id = wc_clean(wp_unslash($_POST['pui_pay_for_order_session_id'] ?? ''));
      if (is_string($pui_pay_for_order_session_id) && $pui_pay_for_order_session_id !== '') {
        return $pui_pay_for_order_session_id;
      }
    }
    $session_id = bin2hex(random_bytes(16));
    WC()->session->set('ppcp_fraudnet_session_id', $session_id);
    return $session_id;
  }
}