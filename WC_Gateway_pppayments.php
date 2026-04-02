<?php

/**
 * Class WC_Gateway_ShieldPP file.
 *
 * @package WooCommerce\Gateways
 */

include_once(plugin_dir_path(__FILE__) . '/libs/shield_api.php');


include_once(plugin_dir_path(__FILE__) . '/vendor/autoload.php');


### PAYPAL API LIBRARY
### API v1
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;

### API v2
//use PayPal\Http\Environment\SandboxEnvironment;
//use PayPal\Http\Environment\ProductionEnvironment;
//use PayPal\Http\PayPalClient;

// use PayPal\Http\AccessTokenRequest;
// use PayPal\Checkout\Requests\OrderAuthorizeRequest;
// use PayPal\Checkout\Requests\OrderCaptureAuth;
// use PayPal\Checkout\Requests\OrderCaptureRequest;
// use PayPal\Checkout\Requests\OrderRefund;
// use PayPalHttp\HttpException;

// Import namespace
// use PayPal\Checkout\Requests\OrderCreateRequest;
// use PayPal\Checkout\Requests\OrderShowRequest;
// use PayPal\Checkout\Orders\AmountBreakdown;
// use PayPal\Checkout\Orders\ApplicationContext;
// use PayPal\Checkout\Orders\Item;
// use PayPal\Checkout\Orders\Order;
// use PayPal\Checkout\Orders\PurchaseUnit;


### API Invoice
use PayPal\Api\BillingInfo;
use PayPal\Api\Currency;
use PayPal\Api\Invoice;
use PayPal\Api\InvoiceItem;
use PayPal\Api\MerchantInfo;
use PayPal\Api\ShippingInfo;

//v2
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesGetRequest;
use Dell\WpShieldpp\ProxyPayPalHttpClient;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * ShieldPP Payment Gateway
 *
 * Provides a ShieldPP Payment Gateway.
 *
 * @class 		WC_Gateway_ShieldPP
 * @extends		WC_Payment_Gateway
 * @version		1.0
 */
class WC_Gateway_pppayments extends WC_Payment_Gateway
{

    /**
     * Gateway instructions that will be added to the thank you page and emails.
     *
     * @var string
     */
    public $instructions;

    public $api_url;
    public $merchant_key;
    public $shield_key;
    public $homeurl;
    //public $path;
    public $api_secret;

    public $shield_config;
    public $current_currency;
    public $multi_currency_enabled;
    public $enabled;
    public $payment_method;
    public $charset;
    public $btn_img;
    public $notify_url;
    public $site_name, $get_version, $apiVersion, $clientId, $Secret;
    public $soap_api, $soap_pass, $soap_signature, $mode, $API_Endpoint, $link_invoice;
    public $version, $path, $business, $waitmess, $completedmess, $debug, $log, $testmode;
    public $shield_info;
    public $fileConfig;
    public $contact_page_link;
    public $hidePaymentUrl = true;

    public $ip_secret = 'b8c7fda7d4d08c118b2e7250fc101cb4';
    
    public function get_icon()
    {
        $icon_html = 'Paypal <img src="https://www.paypalobjects.com/paypal-ui/logos/svg/paypal-color.svg" alt="Paypal" width="149px" height="36px" />';
        return $icon_html;
    }

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->current_currency = get_option('woocommerce_currency');
        $this->multi_currency_enabled = in_array('woocommerce-multilingual/wpml-woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) && get_option('icl_enable_multi_currency') == 'yes';
        $this->charset = strtolower(get_bloginfo('charset'));
        if (!in_array($this->charset, array('gbk', 'utf-8'))) {
            $this->charset = 'utf-8';
        }

        // WooCommerce required settings
        $this->id                 = 'pppayments';
        $this->method_title       = __('TTR Paypal payment', 'ttr_shield_payments');
        $this->method_description = __('Payment with ttr paypal method.', 'ttr_shield_payments');
        $this->has_fields         = false;

       
        $this->btn_img                 = apply_filters('woocommerce_ppcp_btn', plugins_url('assets/images/btn.png', __FILE__));
        $this->has_fields = true;
        $this->order_button_text      = 'Pay Now';
        $this->notify_url             = WC()->api_request_url('wc_shieldpp_ipn');
        $this->enabled = 'yes';

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title                 = $this->get_option('title');
        $this->homeurl                 = get_option('siteurl');

        $this->site_name             = get_option('blogname');
        $this->merchant_key         = get_option('cs_merchant_key');
        $this->shield_key           = get_option('cs_shield_key');
        $this->api_url              = get_option('cs_api_url');


        $this->description            = $this->get_option('description');
        $this->instructions           = $this->get_option('instructions');

        $this->get_version             = $this->get_option('get_version');
        $this->apiVersion            = $this->get_version[0] ?? 'v2';

        $this->clientId             = $this->get_option('API');
        $this->Secret                 = $this->get_option('Secret');

        $this->soap_api             = $this->get_option('SOAP_API');
        $this->soap_pass             = $this->get_option('SOAP_PASS');
        $this->soap_signature         = $this->get_option('SOAP_Signature');
        $this->mode                 = 'LIVE';
        $this->API_Endpoint         = "https://api-3t.paypal.com/nvp";
        $this->link_invoice         = "https://www.paypal.com/invoice/p/#";

        $this->version                 = "124";

        $this->path                 = $this->get_option('path');
        $this->business             = $this->get_option('business');
        $this->contact_page_link    = $this->get_option('contact_page_link');

        $this->waitmess             = $this->get_option('waitmess');
        $this->completedmess         = $this->get_option('completedmess');
        $this->instructions         = $this->completedmess;

        // $this->order_prefix_enabled = $this->get_option('order_prefix_enabled');
        // $this->order_prefix = $this->get_option('orderPrefix');
        $this->debug                 = $this->get_option('debug');
        // Logs
        if ('yes' == $this->debug) {
            $this->log = new WC_Logger();
        }

        $this->testmode = $this->get_option('testmode');
        if ('yes' == $this->testmode) {
            $this->business = 'sb-2yix216053012@business.example.com';
            $this->clientId = 'AQ3VGF0YRugRrryvM4vRKb2ZnAnETjzLutbWC9-sI4A6UyfNGs8pnV3gI5POJDQ_O4ryZ7PotrqwQLCA';
            $this->Secret = "EDkwqnGDsB2VHD-KenTyWDZQaYjd0NH9n7GSvSMTRmXg_JG6bjJXB-y7QbN6M1wdgfQ1uuFCUCbk2NPH";

            $this->soap_api = 'sb-2yix216053012_api1.business.example.com';
            $this->soap_pass = 'ZXEC5DAHMFSR4AUU';
            $this->soap_signature = 'AI9f6AX0wv.h1aOmx81yllsPg4PgAkj5Gy0X3rf7jWL3xzRGZ.kVQ6oE';
            $this->mode = 'sandbox';
            $this->API_Endpoint = "https://api-3t.sandbox.paypal.com/nvp";
            $this->link_invoice = "https://www.sandbox.paypal.com/invoice/p/#";
        }

        // Actions.
        //add_action('woocommerce_api_wc_shieldpp_invoice', array($this, 'invoice')); #http://domain.com/wc-api/wc_shieldpp_invoice/
        add_action('woocommerce_api_woocomerce_paypal_gateway', [ $this, 'handle_paypal_return' ]);
        add_action('woocommerce_api_wc_shieldpp_addorder', array($this, 'add_custom_order')); #http://domain.com/wc-api/wc_shieldpp_addorder/
        add_action('woocommerce_api_wc_shieldpp_ipn', array($this, 'webhook_ipn')); #http://domain.com/wc-api/wc_shieldpp_ipn/

        //TODO:check unused or not
        add_action('woocommerce_api_wc_shieldpp_update_setting', array($this, 'reup_settings')); #http://domain.com/wc-api/wc_shieldpp_update_setting/ 
        add_action('woocommerce_api_wc_shieldpp_setting', array($this, 'shieldpp_setting')); #http://domain.com/wc-api/wc_shieldpp_setting

        add_action('woocommerce_api_wc_ppcp_return', array($this, 'return_page')); #http://domain.com/wc-api/wc_ppcp_return
        add_action('woocommerce_api_wc_return_url', array($this, 'capturePayment')); #http://domain.com/wc-api/wc_ppcp_return
        add_action('woocommerce_api_wc_redirect_payment_link', array($this, 'redirectPaymentLink')); #http://domain.com/wc-api/redirect_payment_link
        
        //generate contat page
        add_action('woocommerce_api_contact', array($this, 'cs_contact')); #http://domain.com/wc-api/contact
        add_action('woocommerce_api_get-in-touch', array($this, 'cs_contact')); #http://domain.com/wc-api/get-in-touch
        add_action('woocommerce_api_reach-out', array($this, 'cs_contact')); #http://domain.com/wc-api/reach-out
        add_action('woocommerce_api_connect-with-us', array($this, 'cs_contact')); #http://domain.com/wc-api/connect-with-us
        add_action('woocommerce_api_talk-to-us', array($this, 'cs_contact')); #http://domain.com/wc-api/talk-to-us
        add_action('woocommerce_api_speak-with-us', array($this, 'cs_contact')); #http://domain.com/wc-api/speak-with-us
        add_action('woocommerce_api_drop-us-a-message', array($this, 'cs_contact')); #http://domain.com/wc-api/api_drop-us-a-message
        add_action('woocommerce_api_send-us-a-message', array($this, 'cs_contact')); #http://domain.com/wc-api/send-us-a-message
        add_action('woocommerce_api_need-help', array($this, 'cs_contact')); #http://domain.com/wc-api/need-help
        add_action('woocommerce_api_support-center', array($this, 'cs_contact')); #http://domain.com/wc-api/support-center
        
        add_action('woocommerce_api_process', array($this, 'cs_process')); #http://domain.com/wc-api/wc_ppcp_return

        add_action('woocommerce_api_wc_cancel', array($this, 'cancel')); #http://domain.com/wc-api/wc_cancel
        add_action('woocommerce_api_test', array($this, 'cs_test')); #http://domain.com/wc-api/test

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options')); //page
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page')); //page
        add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3); //page
        // Customer Emails.
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3); //Page email

        add_action('woocommerce_api_wc_ppcp_message', array($this, 'pending'));
    }

    public function redirectPaymentLink()
    {
        try {
            $pmcode = isset($_GET['pmcode']) ? $_GET['pmcode'] : '';

            if (empty($pmcode)) {
                wp_send_json(array(
                    'status'  => false,
                    'message' => 'Missing required parameters',
                ), 400);
            }

            $paymentLink = pmCodeDecryptToLink($pmcode);
            if (!preg_match('/paypal/', $paymentLink)) {
                wp_send_json(array(
                    'status'  => false,
                    'message' => 'Payment link is error',
                ), 400);
            }
?>
            <html>

            <head>
                <title>Waiting for redirection to the payment link.</title>
            </head>

            <body>
                <div style="width: 100%; text-align: center">
                    <p><img src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/loading_spinner_large.gif' ?>" style="width: 50px" /></p>
                    <p>Waiting for redirection to the payment link.</p>
                    <p><a href="<?php echo $paymentLink ?>" title="Payment link" style="font-weight: bold; color: red">Click here</a>
                        if the system does not redirect automatically.</p>
                </div>
            </body>

            </html>
        <?php
            //telegram_push_log("random_code: $random_code, signature: {$sign}");

            redirect_url($paymentLink, 2);
        } catch (\Exception $e) {
            $message = "Error:\n";
            $message .= "Message: Error in add_custom_order paypal_type = 1" . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";

            plugin_custom_log($message, 'debug.log');
            telegram_push_log($message);

            $res = array(
                'status' => false,
            );
            return $res;
        }
        exit();
    }

    public function shieldpp_setting()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(array('message' => 'Invalid request method'), 405);
        }

        try {
            $post_data = file_get_contents("php://input");
            $jsonData = json_decode($post_data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            telegram_push_log("Setting error: " . $e);
            wp_send_json_error(array('message' => 'Invalid JSON format', 'error' => $e->getMessage()), 400);
        }


        $data = json_decode($jsonData, true);
		
		/* update for paypal */
        $paypalType = $data['paypal']['settings']['paypal_type'] ?? '';
        if ($paypalType == '1') {
            $paypalType = 'v1';
        } else if ($paypalType == '0') {
            $paypalType = 'web';
        } else {
            $paypalType = 'v2';
        }

        $data_paypal = array(
            'get_version'    => [$paypalType],
            'API'            => $data['paypal']['settings']['paypal_client_id'] ?? '',
            'Secret'         => $data['paypal']['settings']['paypal_secret'] ?? '',
            'SOAP_API'       => $data['paypal']['settings']['api_soap_username'] ?? '',
            'SOAP_PASS'      => $data['paypal']['settings']['api_soap_password'] ?? '',
            'SOAP_Signature' => $data['paypal']['settings']['api_soap_signature'] ?? '',
            'business'       => $data['paypal']['settings']['paypal_business_email'] ?? '',
            'testmode'       => isset($data['paypal']['is_sandbox']) && $data['paypal']['is_sandbox'] ? 'yes' : 'no',
        );

        $allowed_settings = array(
            'get_version'       => ['v2'],
            'API'               => '',
            'Secret'            => '',
            'SOAP_API'          => '',
            'SOAP_PASS'         => '',
            'SOAP_Signature'    => '',
            'business'          => '',
            'debug'             => 'yes',
            'testmode'          => '',
            'contact_page_link' => get_random_wc_api_endpoint(),
        );

        foreach ($allowed_settings as $key => $default_value) {
            if(!in_array($key, ['get_version'])) {
                $value = isset($data_paypal[$key]) ? sanitize_text_field($data_paypal[$key]) : $default_value;
            } else {
                $value = isset($data_paypal[$key]) ? $data_paypal[$key] : $default_value;
            }
            
            $this->update_option($key, $value);
        }
		/* end update paypal */
		/* start update stripes */
// 		$data_stripes = array(
// 			'title'             => $data['stripes']['settings']['title'] ?? 'Stripe Payment',
// 			'enabled'			=> isset($data['stripes']['active']) && $data['stripes']['active'] ? 'yes' : 'no',
//             'client_id'    		=> $data['stripes']['settings']['stripes_client_id'] ?? '',
//             'secret'            => $data['stripes']['settings']['stripes_secret'] ?? '',
//             'endpoint_secret'   => $data['stripes']['settings']['stripes_endpoint_secret'] ?? '',
//             'type'       		=> $data['stripes']['settings']['stripes_type'] ?? '',
//             'testmode'       => isset($data['stripes']['is_sandbox']) && $data['stripes']['is_sandbox'] ? 'yes' : 'no',
//         );
		
// 		$allowed_settings = array(
// 			'title'			  => '',
// 			'enabled'		  => 'no',
//             'client_id'       => '',
//             'secret'          => '',
//             'endpoint_secret' => '',
//             'type'            => '',
// 			'testmode'        => '',
//         );
// 		$cs_stripe = new CS_STRIPE();
// 		foreach ($allowed_settings as $key => $default_value) {
//             $value = isset($data_stripes[$key]) ? sanitize_text_field($data_stripes[$key]) : $default_value;
//             $cs_stripe->update_option($key, $value);
//         }
		/* end stripes */
        wp_send_json_success(array('message' => 'Shield settings have been updated'), 200);
    }

    public function reup_settings()
    {
        if (!empty($_REQUEST['mkey']) && !empty($_REQUEST['skey']) && !empty($_REQUEST['url'])) {
            $configs = array(
                'cs_merchant_key' => trim($_REQUEST['mkey']),
                'cs_shield_key'   => trim($_REQUEST['skey']),
                'cs_api_url'      => trim($_REQUEST['url']),
            );

            telegram_push_log("reup_settings config: " . print_r($configs, true));

            foreach ($configs as $key => $value) {
                if (!get_option($key)) {
                    add_option($key, $value, true);
                } else {
                    update_option($key, $value, true);
                }
            }

            $response = array(
                'status'          => true,
                'cs_merchant_key' => get_option('cs_merchant_key'),
                'cs_shield_key'   => get_option('cs_shield_key'),
                'cs_api_url'      => get_option('cs_api_url'),
            );

            wp_send_json($response);
        } else {
            wp_send_json(array(
                'status'  => false,
                'message' => 'Missing required parameters',
            ), 400);
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        //获取返回网址
        $this->form_fields = array(

            'enabled' => array(
                'title' => __('Enable/Disable', 'ttr_shield_payments'),
                'type' => 'checkbox',
                'label' => __('Enable Paypal Payment', 'ttr_shield_payments'),
                'default' => 'yes'
            ),
            'business' => array(
                'title' => __('Paypal Email', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Business Email address', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            'API' => array(
                'title' => __('API', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Enter API Key', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            'Secret' => array(
                'title' => __('Secret', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Enter Secret Key', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            'SOAP_API' => array(
                'title' => __('API User', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Enter API User', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            'SOAP_PASS' => array(
                'title' => __('API Password', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Enter API Password', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            'SOAP_Signature' => array(
                'title' => __('Signature', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Enter Signature', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            'path' => array(
                'title' => __('Return URL', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Enter Return URL', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),

            'get_version' => array(
                'title' => __('Paypal Version', 'ttr_shield_payments'),
                'type' => 'multiselect',
                'description' => __('Please enter the security key', 'ttr_shield_payments'),
                'options'       => array(
                    'v1' => __('Version 1', 'woocommerce'),
                    'v2' => __('Version 2', 'woocommerce'),
                    // 'invoice' => __('Invoice', 'woocommerce' ),
                    // 'invoice2' => __('Invoice v2', 'woocommerce' ),
                    'web' => __('Web', 'woocommerce'),
                ),
                'css' => 'width:400px'
            ),

            'waitmess' => array(
                'title' => __('Waiting Message', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Insert waiting message', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),

            'completedmess' => array(
                'title' => __('Completed Message', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Insert completed message', 'ttr_shield_payments'),
                'default' => '',
                'css' => 'width:400px'
            ),
            
            'contact_page_link' => array(
                'title' => __('Contact page', 'ttr_shield_payments'),
                'type' => 'text',
                'description' => __('Automatic get contact page', 'ttr_shield_payments'),
                'default' => '/wc-api/contact',
                'css' => 'width:400px',
               // 'disabled' => f,
            ),

            'debug' => array(
                'title'       => __('Debug Log', 'ttr_shield_payments'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'ttr_shield_payments'),
                'default'     => 'true',
                'description' => __('Log events, such as trade status.', 'ttr_shield_payments')
            ),

            'testmode' => array(
                'title'       => __('Test Mode', 'ttr_shield_payments'),
                'type'        => 'checkbox',
                'label'       => __('Enable / Disable', 'ttr_shield_payments'),
                'default'     => 'true',
                'description' => __('Enable / Disable test mode.', 'ttr_shield_payments')
            )
        );

        // For WC2.2+
        if (class_exists('WC_Logger')) {
            $logger = wc_get_logger();
            $log_source = 'ttr_shield_payments';
            
            // Ghi log thử để kiểm tra
            $logger->info('Logging initialized for ttr_shield_payments.', ['source' => $log_source]);
        
            $this->form_fields['debug']['description'] = sprintf(
                __('Log events, such as trade status. View logs in WooCommerce > Status > Logs (file: %s).', 'ttr_shield_payments'), 
                $log_source
            );
        }
    }

    /**
     * 附加到页面上的表单数据
     */

    public function payment_fields()
    {
        include __DIR__ . "/tpl/form.php";
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and account etc.
     *
     * @since 1.0
     */
    public function admin_options()
    {
        ?>
        <h3><?php _e('Paypal Payment Gateway', 'ttr_shield_payments'); ?></h3>
        <p><?php _e('Paypal Payment is professional Paypal option for v1, v2, invoice, etc...', 'NoRival'); ?></p>
        <table class="form-table">
            <?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
        </table>
        <!--/.form-table-->
        <?php
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available()
    {
        return true; // (bool)$this->isActiveGateway();
    }

    public function isActiveGateway(): bool
    {
        $is_acitive = false;

        $shield_info = $this->getShieldInfo();

        $shieldStatus = $shield_info['status'] ?? false;
        if ($shieldStatus) {
            $shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
            $is_acitive = $shield_gateways['paypal']['active'];
        }
        return $is_acitive;
    }

    public function getShieldInfo()
    {
        $shieldInfo = get_option('wc_shieldpp_setting');

        $this->shield_info = [
            'status' => true,
            'result' => $shieldInfo,
        ];

        return json_decode($shieldInfo, true);
    }

    public function capturePayment()
    {
        try {
            /**
             * Check and enable WC session
             */
            $paymentId = $_GET['paymentId'] ?? '';
            $token = $_GET['token'] ?? '';
            $payerId = $_GET['PayerID'] ?? '';
            $sign = $_GET['sign'] ?? '';
            $orderId = $_GET['orderId'] ?? 0;
            $transactionId = $paymentId ?? 0;
            $cs_ref_code = $_GET['csRefCode'] ?? 0;
            $invoiceID = $_GET['resource']['invoice']['id'] ?? 0;
    
            $shield_info = $this->getShieldInfo(); //TODO:Cache
            $paymentId = $transactionId;
            //$paypal_settings = array();
            $shieldStatus = $shield_info['status'] ?? false;
            if ($shieldStatus == true) {
                $shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
                //$paypal_settings = $shield_gateways['paypal']['settings'];
            }
    
            $cs_success_url = get_post_meta($orderId, 'mc_success_url', true);
            if (empty($cs_success_url)) {
                $cs_success_url = $shield_gateways['return_link']['cs_success_url'];
            }
    
            $cs_failed_url = get_post_meta($orderId, 'mc_failed_url', true);
            if (empty($cs_failed_url)) {
                $cs_failed_url = $shield_gateways['return_link']['cs_failes_url'];
            }
            try {
                $client = $this->get_paypal_client_type_2();

                ini_set('display_errors', 1);
                ini_set('display_startup_errors', 1);
                error_reporting(E_ALL);
                // echo "<pre>";
                // var_dump($client); die();
                $request = new OrdersCaptureRequest($token);
                
                $request->prefer('return=representation'); // ✅ Thêm prefer nếu cần dữ liệu chi tiết
                
                $response = $client->execute($request);
            } catch (\PayPalHttp\HttpException $e) {
                // PayPal API returned error
                $errorBody = $e->getMessage(); // string
                $statusCode = $e->statusCode;
                
                $message = "PayPal API error during capture:\n";
                $message .= "StatusCode: $statusCode\n";
                $message .= "Response: $errorBody\n";
                plugin_custom_log($message, 'debug.log');
                telegram_push_log($message);
                
                http_response_code($statusCode);
                if (strpos($errorBody, 'INVALID_RESOURCE_ID') !== false) {
                    echo json_encode([
                        'status' => false,
                        'msg'    => 'Access Denied: Invalid resource ID'
                    ]);
                    exit;
                }
            
                echo json_encode([
                    'status' => false,
                    'msg'    => 'PayPal API error: ' . $errorBody
                ]);
                exit;
            } catch (\Exception $e) {
                $message = "Error:\n";
                $message .= "Message: Error in capturePayment paypal_type = 1: " . $e->getMessage() . "\n";
                $message .= "File: " . $e->getFile() . "\n";
                $message .= "Line: " . $e->getLine() . "\n";

                plugin_custom_log($message, 'debug.log');
                telegram_push_log($message);

                $res = [
                    'status' => false,
                    'msg' => $e->getMessage()
                ];

                return $res;
            }

            # Parse result
            $result = $response->result; // 👈 Không cần getBody()

            $payee_email  = $result->purchase_units[0]->payee->email_address ?? '';
            $buyer_email  = $result->payer->email_address ?? '';
            $buyer_status = $result->payer->status ?? '';

            $invoice      = $result->purchase_units[0]->payments->captures[0]->invoice_id ?? '';
            $orderstatus  = $result->purchase_units[0]->payments->captures[0]->status ?? '';
            $txn_id       = $result->purchase_units[0]->payments->captures[0]->id ?? '';

            $logs = [
                'apiInfo'     => [
                    'ppclientId' => $this->clientId,
                    'ppSecret'   => $this->Secret,
                ],
                'payee_email' => $payee_email,
                'buyer_email' => $buyer_email,
                'buyer_status'=> $buyer_status,
                'invoice'     => $invoice,
                'orderstatus' => $orderstatus,
                'txn_id'      => $txn_id
            ];

            $status = '-1'; // default fail

            if ($orderstatus === "COMPLETED") {
                $status = "1";
            } elseif ($orderstatus === "PENDING") {
                $status = "3";
                $reason = $result->purchase_units[0]->payments->captures[0]->status_details->reason ?? '';
                $orderstatus .= ": " . $reason;

                if (strtolower($buyer_status) === "verified") {
                    $status = "1";
                }
            }

            telegram_push_log("Logs: " . print_r($logs, true));

        } catch (\Exception $e) {
            //throw $e;
            
            #Logging
            //file_put_contents($file_logs ,date('Y-m-d H:i:s')." CAN NOT PAY=>".$ex->getMessage().PHP_EOL, FILE_APPEND);

            $message = "Error:\n";
            $message .= "Message: Error in add_custom_order paypal_type = 1" . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";

            plugin_custom_log($message, 'debug.log');
            telegram_push_log($message);

            $res = array(
                'status' => false,
                'msg' => $e->getMessage()
            );
            $this->get_return_url_custom($this->homeurl . "/my-account/");
            return $res;
        }

        try {
            // $or_status = $this->get_nvp_payment_status($txn_id);
            $or_status = $this->get_capture_status($txn_id);
            if (strtolower($buyer_status) == "verified") {
                $or_status = "Completed";
            }
            
            if(preg_match('/(\d+)-(\d+)/', $invoice, $matches)) {
                $invoice = (int)$matches[1]; //invoice_id
            }
            
            $order = wc_get_order($invoice);
            
            $logs2 = [
                'wc_order_status' => $order->get_status(),
                'pp_order_status' => $or_status,
                'invoice_id' => $invoice,
            ];

            if ($order->get_status() == "pending") {
                //telegram_push_log("Log2: " . print_r($logs2, true));
                if ($or_status == "Completed") {
                    $order->add_order_note(sprintf(__('Payment success (Trans ID: %s)', 'wc-ppcp'), $txn_id));
                    $order->payment_complete($txn_id);
                } else if ($or_status == "Pending") {
                    $order->add_order_note(sprintf(__('Payment success (Trans ID: %s)', 'wc-ppcp'), $txn_id));
                    $order->update_status('on-hold', __('Processing', 'woocommerce'));
                } else {
                    $order->add_order_note(sprintf(__('Payment failed (Trans ID: %s)', 'wc-ppcp'), $txn_id));
                    $order->update_status('failed', __('Failed', 'woocommerce'));
                }
            }

            $customer_ip = get_post_meta($orderId, '_customer_ip_address', true);
            $subnet = $this->getSubnetIp();
            $md5 = md5($orderId.$subnet);
            $logs2['subnet'] = $subnet;
            $logs2['orderId'] = $orderId;
            $server_signature = hash_hmac('sha256', $md5, $this->ip_secret);
            //$logs2['payment_code'] = $payment_code;
            $logs2['server_signature'] = $server_signature;
            $logs2['request_sign'] = $sign;
            $logs2['customer_ip'] = $customer_ip;
            $logs2['md5'] = $md5;
            //ip_in_range($customer_ip, $order_ip) != null && 
            if ($server_signature == $sign) {
                $redirectUrl = $cs_success_url;
            } else {
                $redirectUrl = $this->homeurl . "/my-account/";
            }
            
            $logs2['RedirectUrls'] = $redirectUrl;
            //telegram_push_log("Log2: " . print_r($logs2, true));
            
            if(file_exists(__DIR__ . "/tpl/success-page.php")) {
                ob_start();
                require_once __DIR__ . "/tpl/success-page.php";
                $output = ob_get_clean();
                
                if (empty($output)) {
                    die("Output is empty");
                }
                
                // echo raw output
                header('Content-Type: text/html');
                echo $output;
            }
            
            $this->webhook_ipn([
                'order_id' => $orderId,
                'paymentStatus' => $or_status,
                'transaction_id' => $txn_id,
                'cs_ref_code' => $cs_ref_code,
                'invoice_id' => $invoice,
                'payer_email' => $payee_email,
                'buyer_email' => $buyer_email
            ]);
            
            exit;
        } catch (\Exception $e) {
            $message = "Error:\n";
            $message .= "Message: Error in add_custom_order paypal_type = 1" . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";

            plugin_custom_log($message, 'debug.log');
            telegram_push_log($message);

            $res = array(
                'status' => false,
                'msg' => $e->getMessage()
            );
            $this->get_return_url_custom($this->homeurl . "/my-account/");
            return $res;
        }
    }

    private function deformatNVP($nvpstr)
    {
        $intial = 0;
        $nvpArray = array();

        while (strlen($nvpstr)) {
            // postion of Key
            $keypos = strpos($nvpstr, '=');
            // position of value
            $valuepos = strpos($nvpstr, '&') ? strpos($nvpstr, '&') : strlen($nvpstr);

            /* getting the Key and Value values and storing in a Associative Array */
            $keyval = substr($nvpstr, $intial, $keypos);
            $valval = substr($nvpstr, $keypos + 1, $valuepos - $keypos - 1);
            // decoding the respose
            $nvpArray[urldecode($keyval)] = urldecode($valval);
            $nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
        }
        return $nvpArray;
    }

    /**
     * 返回订单号(加了前缀的)
     */
    private function getOrderNo($orderId)
    {
        return $orderId;
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID. 
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $this->getReporterData($order);

        // add Gateway
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $order->set_payment_method($payment_gateways['pppayments']);

        $amount = $order->get_total();
        $currency = get_woocommerce_currency();

        $client = $this->get_paypal_client_type_2();

        $return_url = add_query_arg(
            [
                'wc-api' => 'woocomerce_paypal_gateway',
                'order_id' => $order_id
            ],
            home_url('/')
        );

        // Tạo Order request
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $amount
                ]
            ]],
            'application_context' => [
                'cancel_url' => $order->get_cancel_order_url(),
                'return_url' => $return_url
            ]
        ];

        try {
            $response = $client->execute($request);

            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalUrl = $link->href;
                    break;
                }
            }

            $order->update_status('pending', __('Awaiting PayPal payment', 'ttr_shield_payments'));

            return [
                'result'   => 'success',
                'redirect' => $approvalUrl
            ];
        } catch (Exception $e) {
            wc_add_notice(__('Payment error: ', 'ttr_shield_payments') . $e->getMessage(), 'error');
            return [
                'result'   => 'fail',
                'redirect' => ''
            ];
        }
    }

    public function handle_paypal_return()
    {
        if ( ! isset($_GET['order_id']) || empty($_GET['order_id']) ) {
            wp_die('Order ID missing.');
        }

        $order_id = absint($_GET['order_id']);
        $order = wc_get_order($order_id);

        if ( ! $order ) {
            wp_die('Order not found.');
        }

        $paypal_order_id = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($paypal_order_id)) {
            wp_die('PayPal order ID missing.');
        }

        $client = $this->get_paypal_client_type_2();

        $request = new OrdersCaptureRequest($paypal_order_id);
        $request->prefer('return=representation');

        try {
            $response = $client->execute($request);

            if ($response->result->status === 'COMPLETED') {
                $order->payment_complete();
                $order->add_order_note('PayPal payment completed.');
                wp_safe_redirect($this->get_return_url($order));
                exit;
            } else {
                $order->update_status('failed', 'PayPal payment not completed.');
                wp_safe_redirect(wc_get_page_permalink('cart'));
                exit;
            }
        } catch (Exception $e) {
            $order->update_status('failed', 'PayPal API error: ' . $e->getMessage());
            wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
            wp_safe_redirect(wc_get_page_permalink('cart'));
            exit;
        }
    }

    private function getReporterData($order)
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
            'city'      => $order->get_billing_city(),
            'state'     => $order->get_billing_state(),
            'postcode'  => $order->get_billing_postcode(),
            'country'   => $order->get_billing_country(),
        ];

        $shipping_address = [
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'city'      => $order->get_shipping_city(),
            'state'     => $order->get_shipping_state(),
            'postcode'  => $order->get_shipping_postcode(),
            'country'   => $order->get_shipping_country(),
        ];

        telegram_push_log("Reporter Data\n\n".print_r([
            'email' => $customer_email,
            'ip' => $customer_ip,
            'billing' => $billing_address,
            'shipping' => $shipping_address
        ], true), null, '86593');
    }

    /**
     * Api add custom order
     */
    public function add_custom_order()
    {
        //global $woocommerce, $wpdb;
        try {
            if(empty($_POST)) {
                echo json_encode([
                    'status' => false,
                    'msg'    => 'Access Denied.'
                ]);
                exit;
            }
            $cs_ref_code = $_POST['cs_ref_code'];
            $product_price = (float)$_POST['total_price'];
            $customer_email = $_POST['customer_email'];
            $product_name = $_POST['product_name'];
            $post_id = 0;
            $first_name = $_POST['customer_first_name'];
            $last_name =  $_POST['customer_last_name'];
            $mc_success_url = $_POST['mc_success_url'] ?? '';
            $mc_failed_url = $_POST['mc_failed_url'] ?? '';
            $customer_ip = isset($_POST['customer_ip']) ? $_POST['customer_ip'] : '';

            //telegram_push_log('$customer_ip: '. $customer_ip);

            if ($post_id == 0) {
                $args     = array('post_type' => 'product', 'posts_per_page' => -1);
                $products = get_posts($args);
                $post_id = $products[array_rand($products)]->ID;
                if (!$post_id) {
                    $post_id = 7015;
                }
            }

            $cart_item_data = array(
                'custom_price' => $product_price,
                'custom_product_name' => $product_name
            );

            $address = array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email' => $customer_email
            );

            $order = wc_create_order();
            $product = wc_get_product($post_id);
            $order->set_address($address, 'billing');
            $order->add_order_note("Order process via API CS System");

            // Change the product price
            $product->set_price($product_price);
            $product->set_name($product_name);
            $order->add_product($product, 1, $cart_item_data);
            $order->set_total($product_price);

            // add Gateway
            $payment_gateways = WC()->payment_gateways->payment_gateways();
            $order->set_payment_method($payment_gateways['pppayments']);

            $order->calculate_totals();
            WC()->session->order_awaiting_payment = $order->get_id();

            //make paypal invoice here 
            $order_id = $order->get_id();
            update_post_meta($order_id, 'cs_ref_code', $cs_ref_code);
            update_post_meta($order_id, 'mc_success_url', $mc_success_url);
            update_post_meta($order_id, 'mc_failed_url', $mc_failed_url);

            if ($customer_ip) {
                update_post_meta($order_id, '_customer_ip_address', sanitize_text_field($customer_ip));
            }

            if (!$order_id) {
                wp_send_json([
                    'status' => false,
                    'msg' => 'Can not make order. Please contact admin !'
                ], 200);
            }
            
            $paypalType = $this->get_version[0] ?? 'v2';
            
            //telegram_push_log('ver '. $paypalType);

            if ($paypalType == 'invoice') {
                $payment_res = $this->paypal_type_0();
            } else if ($paypalType == 'v1') {
                $payment_res = $this->paypal_type_1($order, $order_id, $cs_ref_code);
            } else if ($paypalType == 'v2') {
                $payment_res = $this->paypal_type_2($order, $order_id, $cs_ref_code, $customer_ip);
            } else {
                $payment_res = [
                    'status' => false,
                    'msg' => 'Can not choose pp method type'
                ];
            }

            $paymentStatus = $payment_res['status'] ?? false;
            if ($paymentStatus) {
                $payment_invoice_url = $payment_res['payment_link'];

                $redirectUrl = home_url('/wc-api/wc_redirect_payment_link');
                // $redirectUrl = WC()->api_request_url('wc_redirect_payment_link');
                if ($this->hidePaymentUrl) {
                    $payment_invoice_url = add_query_arg([
                        'pmcode' => pmCodeEncryptLinkToCode($payment_invoice_url),
                    ], $redirectUrl);
                }

                $res = array(
                    'status' => true,
                    'result' => array(
                        'shield_ref_code' => $order_id,
                        'payment_link' => $payment_invoice_url,
                        'cs_ref_code' => $cs_ref_code
                    )
                );
            } else {
                $res = array(
                    'status' => false,
                    'msg' => $payment_res['msg']
                );
            }

            wp_send_json($res, 200);
        } catch (\Exception $e) {
            $message = "Error:\n";
            $message .= "Message: Error in add_custom_order paypal_type = 1" . $e->getMessage() . "\n";
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

    private function getSubnetIp($ip = null)
    {
        if(is_null($ip)) {
            $ip = getUserIP();
        }
        
        $ip_parts = explode('.', $ip);
        $subnet = "{$ip_parts[0]}.{$ip_parts[1]}.{$ip_parts[2]}";
        //telegram_push_log("Ip: {$ip}, Subnet: {$subnet}");
        return $subnet;
    }

    private function paypal_type_2($order, $order_id, $cs_ref_code, $custom_ip = null)
    {
        $orderNo = $order_id;
        $total = $order->get_total();

        $this->homeurl = site_url();

        $cancel_url = $this->homeurl . '/wc-api/wc_cancel/';

        if (empty($this->path)) {
            $this->path = $this->homeurl . '/wc-api/wc_return_url?orderId=' . $order_id . '&csRefCode=' . $cs_ref_code;
        }

        $subnet = $this->getSubnetIp($custom_ip);
        $md5 = md5($order_id . $subnet);

        $sign = hash_hmac('sha256', $md5, $this->ip_secret);

        $this->path .= "&sign={$sign}";

        try {
            $client = $this->get_paypal_client_type_2();

            $unique_invoice_id = $orderNo . '-' . time();

            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = [
                "intent" => "CAPTURE",
                "purchase_units" => [
                    [
                        "reference_id" => $orderNo,
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => $total
                        ],
                        "invoice_id" => $unique_invoice_id
                    ]
                ],
                "application_context" => [
                    "cancel_url" => $cancel_url,
                    "return_url" => $this->path
                ]
            ];

            $response = $client->execute($request);

            //' . print_r($response, true));

            $approvalLink = null;
            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalLink = $link->href;
                    break;
                }
            }

            update_post_meta($orderNo, 'paypal_invoice_id', $unique_invoice_id);
            update_post_meta($orderNo, 'cs_pp_payment_link', $approvalLink);

            return [
                'status' => true,
                'payment_link' => $approvalLink,
                'qr_code' => '',
                'cs_ref_code' => $cs_ref_code
            ];
        } catch (\Exception $e) {
            $message = "Error:\n";
            $message .= "Message: Error in paypal_type_2 " . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";

            plugin_custom_log($message, 'debug.log');
            telegram_push_log($message);

            return [
                'status' => false,
                'msg' => $e->getMessage()
            ];
        }
    }

    /**
     * @todo: update sau
     */
    public function paypal_type_0()
    {
        $OAuthTokenCredential = new \PayPal\Auth\OAuthTokenCredential(
            $this->clientId,    // ClientID
            $this->Secret      // Secret
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
                $paylink = "https://www.sandbox.paypal.com/invoice/p/#" . str_replace("-", "", str_replace("INV2-", "", $invoice->getId()));
            } else {
                $paylink = "https://www.paypal.com/invoice/p/#" . str_replace("-", "", str_replace("INV2-", "", $invoice->getId()));
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
            $message .= "Message: Error in create_paypal_invoice paypal_type = 0 cannot create Payment link " . $e->getMessage() . "\n";
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

    private function paypal_type_1($order, $order_id, $cs_ref_code)
    {
        $orderNo = $order_id;
        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->clientId,    // ClientID
                $this->Secret      // Secret
            )
        );
        $apiContext->setConfig(
            array(
                'mode' => $this->mode
            )
        );
        
        //telegram_push_log('$apiContext'. print_r($apiContext, true));

        $payer = new Payer();
        $payer->setPaymentMethod("paypal");

        $amount = new Amount();
        $total = $order->get_total();
        $amount->setCurrency("USD")->setTotal($total); //TODO: set $total

        $transaction = new Transaction();
        $transaction->setAmount($amount)->setDescription(convertSiteUrlToSiteName($this->homeurl) . " Invoice #" . $orderNo)->setInvoiceNumber($orderNo);

        $redirectUrls = new RedirectUrls();
        $cancel_url = $this->homeurl . '/wc-api/wc_cancel/';

        if (empty($this->path)) {
            $this->path = $this->homeurl . '/wc-api/wc_return_url?orderId=' . $order_id . '&csRefCode=' . $cs_ref_code;
        }
        $redirectUrls->setReturnUrl($this->path)->setCancelUrl($cancel_url);

        $payment = new Payment();
        $payment->setIntent("sale")->setPayer($payer)->setRedirectUrls($redirectUrls)->setTransactions(array($transaction));
        try {
            $payment->create($apiContext);
            $redirect_url =  $payment->getApprovalLink();
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            $redirect_url = $this->homeurl;
            throw $ex;
        }

        try {
            update_post_meta($orderNo, 'cs_pp_payment_link', $redirect_url);
            $res = array(
                'status' => true,
                'payment_link' => $redirect_url,
                'qr_code' => '',
                'cs_ref_code' => $cs_ref_code
            );
            return $res;
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            throw $e;
        }
    }

    public function webhook_ipn(array $params = [])
    {
        $defaults = [
            'order_id'       => null,
            'paymentStatus'  => '',
            'transaction_id' => '',
            'cs_ref_code'    => '',
            'invoice_id'     => '',
            'payer_email'     => '',
            'buyer_email'     => ''
        ];

        $params = array_merge($defaults, $params);

        $order_id       = $params['order_id'];
        $paymentStatus  = $params['paymentStatus'];
        $transaction_id = $params['transaction_id'];
        $cs_ref_code    = $params['cs_ref_code'];
        $invoice_id     = $params['invoice_id'];
        $payer_email    = $params['payer_email'];
        $buyer_email    = $params['buyer_email'];

        $message = "Received IPN request\n";

        try {
            if (!is_null($order_id) && !empty($order_id)) {
                $wp_order_id = $order_id;
                //$status = $paymentStatus;
                $data_rs = [];
                $transaction_id = $transaction_id; //? transaction from pmethod
            } else { //invoice
                // $bodyReceived = file_get_contents('php://input');
                // $message .= "POSTBACK IPN: " . $bodyReceived;
                // plugin_custom_log($message, 'debug.log');

                // $data_rs = json_decode($bodyReceived, true);

                // telegram_push_log("data from  body Received: " . print_r($data_rs, true));

                // $wp_order_id = $data_rs['resource']['invoice']['reference'];
                // $invoice_id = $data_rs['resource']['invoice']['id'];

                // //$status = $data_rs['resource']['invoice']['status'];
                // $transaction_id = $data_rs['resource']['invoice']['payments'][0]['transaction_id'];
            }

            $shield_api = new Shield_API(
                $this->api_url,
                $this->merchant_key,
                $this->shield_key
            );

            //telegram_push_log("Shield API: " . print_r($shield_api, true));

            $data_rs = array_merge($data_rs, $params);

            if ($cs_ref_code) { // come from cash shield, post back to api
                $callback_post = array(
                    'type' => 1,
                    'cs_ref_code' => $cs_ref_code,
                    'transaction_id' => $transaction_id, //transaction from pp or stripe return
                    'callback_data' => json_encode($data_rs)
                );

                $webhook_res = $shield_api->webhookIPN($callback_post);

                $message .= "Shield API response: " . json_encode($webhook_res);
                //telegram_push_log("callback_post: " . print_r($message, true));

                //plugin_custom_log($message, 'debug.log');
            } else { //invoice webhoook
                $callback_post = array(
                    'type' => 0,
                    'invoice_id' => $invoice_id,
                    'transaction_id' => $transaction_id, //transaction from pp or stripe return
                    'callback_data' => json_encode($data_rs)
                );

                $webhook_res = $shield_api->webhookIPN($callback_post);

                $message .= "Shield API response: " . json_encode($webhook_res);
                
            }
            telegram_push_log("callback_post: " . print_r($message, true));
        } catch (\Exception $e) {
            $message = "Error:\n";
            $message .= "Message: Error in webhook paypal_type = 1" . $e->getMessage() . "\n";
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
        die();
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }

    public function cancel()
    {
        $redirectUrl = $this->homeurl . "/my-account/";
        
        ob_start();
        include __DIR__ . "/tpl/cancel-page.php";
        $output = ob_get_clean();
        $output = preg_replace('/\s+/', ' ', $output);
        echo trim($output);
        exit();
    }

    public function cs_contact()
    {
        ob_start();
        include __DIR__ . "/tpl/contact-form.php";
        $output = ob_get_clean();
        $output = preg_replace('/\s+/', ' ', $output);
        echo trim($output);
        exit();
    }

    public function cs_process()
    {
        $currentSign = $_GET['signature'] ?? '';
        echo "signature: $currentSign";
        echo "<br />";
        echo "signature: $currentSign";
        echo "<br />";
        echo "signature: $currentSign";
        echo "<br />";
        echo "signature: $currentSign";
        echo "<br />";

        $payment_code = WC()->session->get('payment', 0);
        $signature = genSignature($this->ip_secret, $payment_code);
        if ($signature === $currentSign) {
            echo 'OK';
        } else {
            echo $signature;
            echo "<br />";
            echo 'not ok';
        }
        exit();
    }

    public function cs_test()
    {
        if (! WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        $payment_code = WC()->session->get('payment', 0);
        $sign = WC()->session->get('signature', 0);

        echo $payment_code;
        echo "<br />";
        echo $sign;
        echo "<Br />";

        $order_ip = json_decode(file_get_contents('https://premiumkey.co/tdev/checkproxy.php?ip=' . getUserIP()))->iprange;
        echo $order_ip;
        echo "<br />";

        $myip = '222.253.249.206';

        if (WC()->session->get('payment', 0)) {
            $payment_code = WC()->session->get('payment', 0);
        } else {
            $payment_code = null;
        }

        if (WC()->session->get('signature', 0)) {
            $server_signature = WC()->session->get('signature', 0);
        } else {
            $server_signature = null;
        }

        $md5 = md5($payment_code . $this->ip_secret);

        if (ip_in_range($myip, $order_ip) != null && ($server_signature == $md5)) {
            echo 123;
        } else {
            echo 456;
        }

        die();
        echo $payment_code;
        echo "<br />";
        echo genSignature($this->ip_secret, $payment_code);
        die();
    }

    /**
     * Change payment complete order status to completed for COD orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        if ($order && 'cod' === $order->get_payment_method()) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }
    
    public function get_return_url_custom($url = '')
    {
        $redirectUrl = $url;
        if(empty($url)) {
            $redirectUrl = $this->homeurl . "/my-account/";
        }
        wp_redirect( $redirectUrl, 301 ); exit;
    }

    private function get_capture_status($txn_id) {
        try {
            $client = $this->get_paypal_client_type_2();
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
        } catch (\PayPalHttp\HttpException $e) {
            // PayPal API returned error
            $errorBody = $e->getMessage(); // string
            $statusCode = $e->statusCode;
            
            $message = "PayPal API error get capture info:\n";
            $message .= "StatusCode: $statusCode\n";
            $message .= "Response: $errorBody\n";
            plugin_custom_log($message, 'debug.log');
            telegram_push_log($message);
            
            http_response_code($statusCode);
            if (strpos($errorBody, 'INVALID_RESOURCE_ID') !== false) {
                echo json_encode([
                    'status' => false,
                    'msg'    => 'Access Denied: Invalid resource ID'
                ]);
                exit;
            }
        
            echo json_encode([
                'status' => false,
                'msg'    => 'PayPal API error: ' . $errorBody
            ]);
            exit;
        }
    }

    private function get_nvp_payment_status($txn_id) {
        ### Check order status
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->API_Endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        // Proxy
        $proxyHostPort = "94.103.59.95:46547";
        $proxyAuth     = "Jddr35mO3nJ322r:3MPnXtrJ0DGa1d9";
        $proxyType     = CURLPROXY_SOCKS5_HOSTNAME;
        curl_setopt($ch, CURLOPT_PROXY, $proxyHostPort);
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);

        // Optional but often useful for SOCKS
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);


        // NVPRequest for submitting to server
        $nvpreq = "METHOD=GetTransactionDetails" . "&TRANSACTIONID=" . $txn_id . "&VERSION=124&PWD=" . $this->soap_pass . "&USER=" . $this->soap_api . "&SIGNATURE=" . $this->soap_signature;
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
        $response = curl_exec($ch);
        
        //telegram_push_log("rsp2: ". print_r($response, true));
        
        $nvpResArray = $this->deformatNVP($response);
        curl_close($ch);

        $or_status = $nvpResArray['PAYMENTSTATUS'];
        return $or_status;
    }

    private function get_paypal_client_type_2() {
        if ('yes' == $this->testmode) {
            $environment = new SandboxEnvironment($this->clientId, $this->Secret);
        } else {
            $environment = new ProductionEnvironment($this->clientId, $this->Secret);
        }
        $client = new PayPalHttpClient($environment);
        $client = new ProxyPayPalHttpClient($environment);

        $proxyHostPort = "94.103.59.95:46547";
        $proxyAuth     = "Jddr35mO3nJ322r:3MPnXtrJ0DGa1d9";
        $proxyType     = CURLPROXY_SOCKS5_HOSTNAME;
        $client->setProxy($proxyHostPort, $proxyAuth, $proxyType);

        return $client;
    }
}
