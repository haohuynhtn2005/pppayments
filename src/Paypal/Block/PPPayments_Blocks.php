<?php
namespace ShieldPpPayment\Paypal\Block;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use ShieldPpPayment\Library\CsPluginConfig;

class PPPayments_Blocks extends AbstractPaymentMethodType
{
  public function __construct()
  {
    $this->name = CsPluginConfig::get('plugin.id');
  }

  public function initialize()
  {
    // $this->settings = get_option('woocommerce_pppayments_settings', []);
  }

  public function is_active()
  {
    return true;
  }

  public function get_payment_method_script_handles()
  {
    $src =
      plugins_url('/assets/js/blocks.js', CsPluginConfig::get('plugin.plugin_startup_file'));
    wp_register_script(
      'pppayments-blocks',
      $src,
      [
        'wc-blocks-registry',
        'wc-settings',
        'wp-element',
        'wp-html-entities',
        'wp-i18n',
      ],
      '1.0.0',
      true
    );

    return ['pppayments-blocks'];
  }

  public function get_payment_method_data()
  {
    return [
      'title' => 'Paypal',
      'description' => 'Pay with PayPal.',
      'supports' => ['products'],
    ];
  }
}
