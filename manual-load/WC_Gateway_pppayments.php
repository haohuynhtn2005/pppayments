<?php
/**
 * Class WC_Gateway_ShieldPP file.
 *
 * @package WooCommerce\Gateways
 */

// Exit if accessed directly.
if (!defined( 'ABSPATH' )) {
    exit;
}

//v2
use PayPalHttp\HttpException;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesGetRequest;

use ShieldPpPayment\Helper\ClientIpHelper;
use ShieldPpPayment\Library\CsPluginConfig;
use ShieldPpPayment\Paypal\PaypalClient;
use ShieldPpPayment\Paypal\PaypalType0;
use ShieldPpPayment\Paypal\PaypalType1;
use ShieldPpPayment\Paypal\PaypalType2;
use ShieldPpPayment\Paypal\PpaymentsPluginConfigDto;
use ShieldPpPayment\Service\ConfigFormFieldService;
use ShieldPpPayment\Service\ErrorHandler;
use ShieldPpPayment\Service\ReportDataService;
use ShieldPpPayment\Response\FailedResponseDto;
use ShieldPpPayment\Service\Order\CsOrderService;
use ShieldPpPayment\Service\Shield\ShieldSettingService;

class WC_Gateway_pppayments extends WC_Payment_Gateway
{
    private $path;
    /**
     * @deprecated V2 only
     */
    private $apiVersion;
    private $debug;
    private $testmode;

    private $invoiceIdPrefix;
    private $business;
    private $clientId;
    private $Secret;
    /**
     * @deprecated Use the new API client instead.
     */
    private $soap_api;
    /**
     * @deprecated Use the new API client instead.
     */
    private $soap_pass;
    /**
     * @deprecated Use the new API client instead.
     */
    private $soap_signature;

    private $waitmess;
    private $completedmess;

    private $pluginSecret = '';
    private $contact_page_link = '';

    public function __construct()
    {
        // WooCommerce required settings
        $this->id                 = CsPluginConfig::get('plugin.id');
        $this->method_title       = __('TTR Paypal payment', 'ttr_shield_payments');
        $this->method_description = __('Payment with ttr paypal method.', 'ttr_shield_payments');
        $this->has_fields = true;

        $this->title                  = 'Paypal';
        $this->description            = '';
        $this->order_button_text      = 'Proceed to pay';

        // Load the settings.
        $this->init_settings();
        $this->init_form_fields();

        // $this->btn_img = apply_filters(
        //     'woocommerce_ppcp_btn',
        //     plugins_url('assets/images/btn.png', __FILE__)
        // );
        // $this->notify_url = WC()->api_request_url('wc_shieldpp_ipn');
        // $this->current_currency = get_option('woocommerce_currency');
        // $this->multi_currency_enabled = in_array(
        //     'woocommerce-multilingual/wpml-woocommerce.php',
        //     apply_filters('active_plugins', get_option('active_plugins'))
        // ) && get_option('icl_enable_multi_currency') == 'yes';

        $this->pluginSecret         = CsPluginConfig::get('plugin.secret');
        $this->contact_page_link    = WC()->api_request_url('contact');

        $this->path                 = $this->get_option('path');
        // $get_version                = $this->get_option('get_version');
        // $this->apiVersion           = $get_version[0] ?? 'v2';
        $this->apiVersion           = 'v2';

        $this->invoiceIdPrefix      = $this->get_option('invoice_id_prefix');
        $this->debug                = $this->get_option('debug');
        $this->testmode             = $this->get_option('testmode');

        $this->business             = $this->get_option('business');
        $this->clientId             = $this->get_option('API');
        $this->Secret               = $this->get_option('Secret');

        $this->soap_api             = $this->get_option('SOAP_API');
        $this->soap_pass            = $this->get_option('SOAP_PASS');
        $this->soap_signature       = $this->get_option('SOAP_Signature');

        $this->waitmess             = $this->get_option('waitmess');
        $this->completedmess        = $this->get_option('completedmess');
        // $this->config = PpaymentsPluginConfigDto::fromOptions(
        //     fn (string $key, $empty_value = null) => $this->get_option($key, $empty_value)
        // );

        if ($this->isTestMode()) {
            $this->business = 'sb-2yix216053012@business.example.com';
            $this->clientId = 'AQ3VGF0YRugRrryvM4vRKb2ZnAnETjzLutbWC9-sI4A6UyfNGs8pnV3gI5POJDQ_O4ryZ7PotrqwQLCA';
            $this->Secret = "EDkwqnGDsB2VHD-KenTyWDZQaYjd0NH9n7GSvSMTRmXg_JG6bjJXB-y7QbN6M1wdgfQ1uuFCUCbk2NPH";

            $this->soap_api = 'sb-2yix216053012_api1.business.example.com';
            $this->soap_pass = 'ZXEC5DAHMFSR4AUU';
            $this->soap_signature = 'AI9f6AX0wv.h1aOmx81yllsPg4PgAkj5Gy0X3rf7jWL3xzRGZ.kVQ6oE';
        }

        // Actions.
        // used by CS
        // http://domain.com/wc-api/wc_shieldpp_addorder
        add_action('woocommerce_api_wc_shieldpp_addorder', [$this, 'add_custom_order']);
        // http://domain.com/wc-api/wc_shieldpp_getorder
        add_action('woocommerce_api_wc_shieldpp_getorder', [$this, 'get_order']);
        // http://domain.com/wc-api/wc_shieldpp_setting
        add_action('woocommerce_api_wc_shieldpp_setting', [$this, 'shieldpp_setting']);
        // end used by CS
        // TODO: check unused or not
        // http://domain.com/wc-api/wc_shieldpp_update_setting
        add_action('woocommerce_api_wc_shieldpp_update_setting', [$this, 'reup_settings']);

        // http://domain.com/wc-api/redirect_payment_link
        add_action('woocommerce_api_wc_redirect_payment_link', [$this, 'redirect_payment_link']);
        // http://domain.com/wc-api/wc_return_url
        add_action('woocommerce_api_wc_return_url', [$this, 'capture_payment']);
        // http://domain.com/wc-api/wc_cancel
        add_action('woocommerce_api_wc_cancel', [$this, 'cs_cancel']);
        // http://domain.com/wc-api/contact
        add_action('woocommerce_api_contact', [$this, 'cs_contact']);
        /**
         * @deprecated Not used by CS
         */
        // http://domain.com/wc-api/test
        add_action('woocommerce_api_test', [$this, 'cs_test']);
        /**
         * @deprecated Not used by CS
         */
        // http://domain.com/wc-api/process
        add_action('woocommerce_api_process', [$this, 'cs_process']);
        add_action('woocommerce_api_woocomerce_paypal_gateway', [$this, 'handle_paypal_return']);

        // // http://domain.com/wc-api/wc_shieldpp_invoice
        // add_action('woocommerce_api_wc_shieldpp_invoice', [$this, 'invoice']);
        // // http://domain.com/wc-api/wc_ppcp_return
        // add_action('woocommerce_api_wc_ppcp_return', [$this, 'return_page']);
        // http://domain.com/wc-api/woocomerce_paypal_gateway

        // Add text at completed payment page
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        // Add content to WC emails
        add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);
        // Hook to update options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        // Hook to update order status when complete payment
        add_filter('woocommerce_payment_complete_order_status', [$this, 'change_payment_complete_order_status'], 10, 3);
    }

    public function init_form_fields()
    {
        $this->form_fields = ConfigFormFieldService::getFormFields();
        $key = "invoice_id_prefix";
        if (!\array_key_exists($key, $this->settings)) {
            $generatedPrefix = ConfigFormFieldService::genInvoicePrefix();
            $this->update_option($key, $generatedPrefix);
        }
    }

    public function get_icon()
    {
        $icon_html = <<<HTML
        <img
            alt="Paypal"
            src="https://www.paypalobjects.com/paypal-ui/logos/svg/paypal-color.svg"
            style="
                height: 1.5em;
            "
        />
        HTML;
        return $icon_html;
    }

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available()
    {
        $isAvailable = $this->enabled == 'yes';
        // $isAvailable = true;
        return $isAvailable;
    }

    public function payment_fields()
    {
        $this->loadTemplate('fields.php', []);
    }

    /**
     * For PaymentGateway getting state
     */
    public function is_test_mode_onboarding()
    {
        $isTestMode = 'yes' == $this->testmode;
        return $isTestMode;
    }

    /**
     * @deprecated Use default instead
     */
    public function admin_options()
    {
        return parent::admin_options();
        $settingHtml = $this->generate_settings_html([], false);
        $title = __('Paypal Payment Gateway', 'ttr_shield_payments');
        $desc =
            __(
                'Paypal Payment is professional Paypal option for v1, v2, invoice, etc...',
                'ttr_shield_payments'
            );
        $html = <<<HTML
        <h3>$title</h3>
        <p>$desc</p>
        <table class="form-table">
            $settingHtml
        </table>
        HTML;
        echo $html;
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->completedmess) {
            echo wp_kses_post(wpautop(wptexturize($this->completedmess)));
        }
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
        if ($this->completedmess && !$sent_to_admin
            && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->completedmess)) . PHP_EOL);
        }
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
        $reportDataSrv = new ReportDataService();
        $reportDataSrv->reportData($order);

        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $order->set_payment_method($payment_gateways['pppayments']);
        $amount = $order->get_total();
        $currency = get_woocommerce_currency();

        $return_url = add_query_arg(
            [
                'wc-api' => 'woocomerce_paypal_gateway',
                'order_id' => $order_id
            ],
            home_url('/')
        );

        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount
                    ]
                ]
            ],
            'application_context' => [
                'return_url' => $return_url,
                'cancel_url' => $order->get_cancel_order_url(),
            ]
        ];

        try {
            $client = $this->get_paypal_client_type_2();
            $response = $client->execute($request);
            $order->update_status('pending', __('Awaiting PayPal payment', 'ttr_shield_payments'));

            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalUrl = $link->href;
                    break;
                }
            }

            return [
                'result'   => 'success',
                'redirect' => $approvalUrl
            ];
        } catch (Throwable $th) {
            wc_add_notice(__('Payment error: ', 'ttr_shield_payments') . $th->getMessage(), 'error');
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

        try {
            $client = $this->get_paypal_client_type_2();
            $request = new OrdersCaptureRequest($paypal_order_id);
            $request->prefer('return=representation');
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
        } catch (Throwable $th) {
            $order->update_status('failed', 'PayPal API error: ' . $th->getMessage());
            wc_add_notice('Payment failed: ' . $th->getMessage(), 'error');
            wp_safe_redirect(wc_get_page_permalink('cart'));
            exit;
        }
    }

    public function reup_settings()
    {
        if (
            empty($_REQUEST['mkey']) || empty($_REQUEST['skey'])
            || empty($_REQUEST['url'])
        ) {
            wp_send_json(array(
                'status'  => false,
                'message' => 'Missing required parameters',
            ), 400);
        }

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
    }

    public function shieldpp_setting()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(array('message' => 'Invalid request method'), 405);
        }
        $post_data = file_get_contents("php://input");
        $jsonData = json_decode($post_data, true);

        $data = (array) $jsonData;
        if (\is_string($jsonData)) {
            $data = (array) json_decode($jsonData, true);
        }

        $shieldSettingSrv = new ShieldSettingService();
        $processedSettings =
            $shieldSettingSrv->processShieldSettingData($this->settings, $data);

        foreach ($processedSettings as $key => $val) {
            $this->update_option($key, $val);
        }

        $shieldSettingSrv->updateStripeSetting($data);
        wp_send_json_success(
            ['message' => 'Shield settings have been updated'],
            200
        );
    }

    public function redirect_payment_link()
    {
        try {
            $pmcode = $_GET['pmcode'] ?? '';
            $paymentLink = decodePmCode($pmcode);
            $paypalPaymentLink = (string) $paymentLink;
            $rawQuery = parse_url($paypalPaymentLink, PHP_URL_QUERY);
            $query = (string) $rawQuery;
            parse_str($query, $params);
            $token = $params['token'] ?? null;
            $captureUrl = WC()->api_request_url('shield_pp_capture');
            $captureUrl = WC()->api_request_url('wc_return_url');

            $data = [
                'token' => $token,
                'paymentLink' => $paymentLink,
                'clientId' => $this->clientId,
                'captureUrl' => $captureUrl,
            ];
            ob_start();
            $this->loadTemplate('redirect-page.php', $data);
            $output = ob_get_clean();
            echo $output;
            exit();
        } catch (Throwable $th) {
            $this->handleGeneralError($th);
        }
    }

    public function add_custom_order()
    {
        try {
            if(!$_POST) {
                wp_send_json([
                    'status' => false,
                    'msg'    => 'Access denied.'
                ]);
                exit;
            }
            $cs_ref_code = $_POST['cs_ref_code'];
            $targetPrice = (float) $_POST['total_price'];
            $customer_email = $_POST['customer_email'];
            $product_name = $_POST['product_name'];
            $first_name = $_POST['customer_first_name'];
            $last_name = $_POST['customer_last_name'];
            $mc_success_url = $_POST['mc_success_url'] ?? '';
            $mc_failed_url = $_POST['mc_failed_url'] ?? '';
            $customer_ip = $_POST['customer_ip'] ?? '';

            $csOrderSrv = new CsOrderService();
            $order = $csOrderSrv->createOrder(
                $cs_ref_code,
                $targetPrice,
                $customer_email,
                $product_name,
                $first_name,
                $last_name,
                $mc_success_url,
                $mc_failed_url,
                $customer_ip
            );

            $order_id = $order->get_id();
            if (!$order_id) {
                wp_send_json([
                    'status' => false,
                    'msg' => 'Can not make order. Please contact admin!'
                ], 200);
            }

            $payment_res = $this->createPaypalPayment($order, $cs_ref_code, $customer_ip);
            $paymentStatus = $payment_res['status'] ?? false;
            if (!$paymentStatus) {
                $res = array(
                    'status' => false,
                    'msg' => $payment_res['msg']
                );
            } else {
                // $redirectUrl = home_url('/wc-api/wc_redirect_payment_link');
                $redirectUrl = WC()->api_request_url('wc_redirect_payment_link');
                $rootPaymentLink = $payment_res['payment_link'];
                $paymentLink = add_query_arg([
                    'pmcode' => encodePmCode($rootPaymentLink),
                ], $redirectUrl);

                $res = array(
                    'status' => true,
                    'result' => array(
                        'cs_ref_code' => $cs_ref_code,
                        'shield_ref_code' => $order_id,
                        'payment_link' => $paymentLink,
                    )
                );
            }

            wp_send_json($res, 200);
        } catch (Throwable $th) {
            $this->responseOnGeneralError($th);
        }
    }

    public function get_order()
    {
        try {
            $rawOrderId = $_GET['order_id'] ?? null;
            $orderId = (int) $rawOrderId;
            if (!$orderId) {
                $this->failedResponse(
                    new FailedResponseDto('Order ID is required'),
                    400
                );
            }

            $post = get_post($orderId);
            if (!$post) {
                $this->failedResponse(
                    new FailedResponseDto('Order not found'),
                    404
                );
            }

            $rawPaypalPaymentLink = get_post_meta($orderId, 'cs_pp_payment_link', true);
            // $rawPaypalPaymentLink = '';
            $paypalPaymentLink = (string) $rawPaypalPaymentLink;
            $rawQuery = parse_url($paypalPaymentLink, PHP_URL_QUERY);
            $query = (string) $rawQuery;
            parse_str($query, $params);
            $token = $params['token'] ?? null;

            $paypalOrderId = (string) $token;
            if (!$paypalOrderId) {
                $this->failedResponse(
                    new FailedResponseDto('Paypal order ID not found'),
                    422
                );
            }

            $request = new OrdersGetRequest($paypalOrderId);
            $client = $this->get_paypal_client_type_2();
            $response = $client->execute($request);
            $result = $response->result;
            $res = [
                'status' => true,
                'data' => $result,
            ];
            wp_send_json($res, 200);
        } catch (HttpException $e) {
            $res = [
                'status' => false,
                'msg' => $e->getMessage(),
            ];
            wp_send_json($res, $e->statusCode);
            if ($e->statusCode != 404) {
                $this->handleGeneralError($e);
            }
        } catch (Throwable $th) {
            $this->responseOnGeneralError($th);
        }
    }

    public function capture_payment()
    {
        try {
            $token = $_GET['token'] ?? '';
            $payerId = $_GET['PayerID'] ?? '';
            // $cs_ref_code = $_GET['csRefCode'] ?? 0;

            $pluginConfig = $this->getPluginConfig();
            $csOrderSrv = new CsOrderService();
            $order = $csOrderSrv->capturePayment(
                $token,
                $pluginConfig,
            );
            $orderId = $order->get_id();
            $orderIdFromQuery = $orderId;

            $shield_gateways = $this->getShieldPpSetting();
            $cs_success_url = get_post_meta($orderIdFromQuery, 'mc_success_url', true);
            if (!$cs_success_url) {
                $cs_success_url = $shield_gateways['return_link']['cs_success_url'];
            }
            $cs_failed_url = get_post_meta($orderIdFromQuery, 'mc_failed_url', true);
            if (!$cs_failed_url) {
                $cs_failed_url = $shield_gateways['return_link']['cs_failes_url'];
            }

            $redirectUrl = $cs_success_url;
            $isValidClient = $csOrderSrv->isValidClient($order);
            if (!$isValidClient) {
                $redirectUrl = $this->getMyAccountUrl();
            }

            $data = [
                'redirectUrl' => $redirectUrl,
                'contact_page_link' => $this->contact_page_link,
            ];
            ob_start();
            $this->loadTemplate('success-page.php', $data);
            $output = ob_get_clean();
            echo $output;
            exit();
        } catch (HttpException $e) {
            $errorBody = $e->getMessage();
            $statusCode = $e->statusCode;

            $message = "PayPal API error during capture:\n";
            $message .= "StatusCode: $statusCode\n";
            $message .= "Response: $errorBody\n";
            plugin_custom_log($message);
            telegram_push_log($message);

            $failRes = [
                'status' => false,
                'msg' => 'PayPal API error: ' . $errorBody
            ];
            wp_send_json($failRes, $statusCode);
            exit;
        } catch (Throwable $th) {
            $this->handleGeneralError($th);
            $redirectUrl = $this->getMyAccountUrl();
            wp_redirect($redirectUrl, 301);
            exit;
        }
    }

    private function getShieldPpSetting()
    {
        $rawShieldInfo = get_option('wc_shieldpp_setting');
        $shield_info = json_decode($rawShieldInfo, true);
        $shieldStatus = $shield_info['status'] ?? false;
        $shield_gateways = [];
        if ($shieldStatus) {
            $shield_gateways = (array) json_decode($shield_info['result']['shield_gateways'], true);
        }
        return $shield_gateways;
    }

    private function createPaypalPayment($order, $cs_ref_code, $customer_ip)
    {
        $payment_res = [
            'status' => false,
            'msg' => 'Can not choose pp method type'
        ];

        $paypalType = $this->apiVersion;
        if ($paypalType == 'invoice') {
            $payment_res = $this->paypal_type_0();
        } else if ($paypalType == 'v1') {
            $payment_res = $this->paypal_type_1($order, $cs_ref_code);
        } else if ($paypalType == 'v2') {
            $payment_res = $this->paypal_type_2($order, $cs_ref_code, $customer_ip);
        }
        return $payment_res;
    }

    private function paypal_type_0()
    {
        $ppType0 = new PaypalType0(
            $this->clientId,
            $this->Secret,
            $this->isTestMode(),
        );
        $res = $ppType0->createType0Order();
        return $res;
    }

    private function paypal_type_1($order, $cs_ref_code)
    {
        $ppType1 = new PaypalType1(
            $this->path,
            $this->isTestMode(),
            $this->clientId,
            $this->Secret
        );
        $res = $ppType1->createType1Order($order, $cs_ref_code);
        return $res;
    }

    private function paypal_type_2($order, $cs_ref_code, $customer_ip)
    {
        $client = $this->get_paypal_client_type_2();
        $pptype2 = new PaypalType2(
            $client,
            $this->path,
            $this->invoiceIdPrefix,
        );
        return $pptype2->createOrder($order, $cs_ref_code, $customer_ip);
    }

    public function cs_cancel()
    {
        $homeUrl = get_option('siteurl');
        $redirectUrl = "$homeUrl/my-account/";
        $data = [
            'contact_page_link' => $this->contact_page_link,
            'redirectUrl' => $redirectUrl,
        ];
        ob_start();
        $this->loadTemplate('cancel-page.php', $data);
        $output = ob_get_clean();
        $output = preg_replace('/\s+/', ' ', $output);
        echo trim($output);
        exit();
    }

    public function cs_contact()
    {
        $sendMsgUrl = rest_url('contact/v1/send');
        ob_start();
        $data = [
            'business_email' => $this->business,
            'send_msg_url' => $sendMsgUrl,
        ];
        $this->loadTemplate('contact-page.php', $data);
        $output = ob_get_clean();
        $output = preg_replace('/\s+/', ' ', $output);
        echo trim($output);
        exit();
    }

    public function cs_process()
    {
        return;
        $currentSign = $_GET['signature'] ?? '';
        echo "signature: $currentSign";
        echo "<br />";

        $payment_code = WC()->session->get('payment', 0);
        $key = $this->pluginSecret;
        $clientIp = ClientIpHelper::getClientIp();
        $str = $clientIp . $key . $payment_code;
        $signature = md5($str);
        echo $signature;
        echo "<br />";

        if ($signature === $currentSign) {
            echo 'OK';
        } else {
            echo 'not ok';
        }
    }

    public function cs_test()
    {
        return;
        if (! WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        $sign = WC()->session->get('signature', 0);
        $payment_code = WC()->session->get('payment', 0);

        echo $payment_code;
        echo "<br />";
        echo $sign;
        echo "<Br />";

        $clientIp = ClientIpHelper::getClientIp();
        $order_ip =
            json_decode(file_get_contents('https://premiumkey.co/tdev/checkproxy.php?ip=' . $clientIp))->iprange;
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
        $md5 = md5($payment_code . $this->pluginSecret);

        if(!function_exists('ip_in_range')) {
            function ip_in_range( $ip, $range )
            {
                if ( strpos( $range, '/' ) == false ) {
                    $range .= '/32';
                }
                // $range is in IP/CIDR format eg 127.0.0.1/24
                list( $range, $netmask ) = explode( '/', $range, 2 );
                $range_decimal = ip2long( $range );
                $ip_decimal = ip2long( $ip );
                $wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
                $netmask_decimal = ~ $wildcard_decimal;
                return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
            }
        }
        if (ip_in_range($myip, $order_ip) != null && ($server_signature == $md5)) {
            echo 123;
        } else {
            echo 456;
        }
    }

    private function getMyAccountUrl()
    {
        $homeUrl = get_option('siteurl');
        $redirectUrl = "$homeUrl/my-account/";
        return $redirectUrl;
    }

    private function handleGeneralError(Throwable $th)
    {
        ErrorHandler::handleGeneralError($th);
    }

    private function responseOnGeneralError(Throwable $th)
    {
        $this->handleGeneralError($th);
        $res = [
            'status' => false,
            'msg' => $th->getMessage(),
        ];
        wp_send_json($res, 500);
    }

    private function failedResponse(FailedResponseDto $dto, int $status): never
    {
        $res = [
            'status' => false,
            'msg'    => $dto->msg,
        ];

        if ($dto->code !== null) {
            $res['code'] = $dto->code;
        }

        if ($dto->data !== null) {
            $res['data'] = $dto->data;
        }

        wp_send_json($res, $status);
        exit;
    }

    private function loadTemplate(string $template, array $data)
    {
        $pluginFile = CsPluginConfig::get('plugin.plugin_startup_file');
        $pluginDir = plugin_dir_path($pluginFile);
        $pluginDir = rtrim($pluginDir, '/\\');
        extract($data);
        require_once $pluginDir . "/tpl/$template";
    }

    private function isTestMode()
    {
        $isTesting = 'yes' == $this->testmode;
        return $isTesting;
    }

    private function get_paypal_client_type_2()
    {
        $pluginConfig = $this->getPluginConfig();
        $paypalCt = new PaypalClient();
        return $paypalCt->getPaypalClientV2($pluginConfig);
    }

    public function getPluginConfig()
    {
        $config = PpaymentsPluginConfigDto::fromOptions(
            fn (string $key, $empty_value = null)
                => $this->get_option($key, $empty_value)
        );
        return $config;
    }
}
