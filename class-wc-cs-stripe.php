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
include_once (plugin_dir_path( __FILE__ ).'vendor/stripe-php/init.php');

/**
 * CS_STRIPE Payment Gateway
 *
 * Provides a CS_STRIPE Payment Gateway.
 *
 * @class 		WC_Gateway_CS_STRIPE
 * @extends		WC_Payment_Gateway
 * @version		1.0
 */
class CS_STRIPE extends WC_Payment_Gateway
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
	public $stripes_client_id;
	public $stripes_secret;
	public $stripes_endpoint_secret;
	public $stripes_type; // direct,checkout,paymentlink

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
		
		
		
		$this->stripes_client_id       	= $this->get_option('client_id');
		$this->stripes_secret       	= $this->get_option('secret');
		$this->stripes_endpoint_secret  = $this->get_option('endpoint_secret');
		$this->stripes_type       	  	= $this->get_option('type');
		
		$this->testmode					= $this->get_option('testmode');
		//$this->stripes_type ="direct"; //checkout,paymentlink,direct

		$file_configs = plugin_dir_path(__FILE__) . "configs_110201.txt";
		$configs = json_decode(file_get_contents($file_configs), true);
		if (!empty($configs['cs_merchant_key'])) {
			$this->api_url              = $configs['cs_api_url'];
			$this->merchant_key         = $configs['cs_merchant_key'];
			$this->shield_key           = $configs['cs_shield_key'];
		} else {
			if (!empty(get_option('cs_merchant_key'))) {
				$this->api_url              = get_option('cs_api_url');
				$this->merchant_key         = get_option('cs_merchant_key');
				$this->shield_key           = get_option('cs_shield_key');
			} else {
				$this->api_url              = $this->get_option('api_url');
				$this->merchant_key         = $this->get_option('merchant_key');
				$this->shield_key           = $this->get_option('shield_key');
			}
		}


		// Actions.		
		add_action('woocommerce_api_cs_stripe_invoice', array($this, 'invoice'));				#Invoice URL
		add_action('woocommerce_api_cs_stripe_ipn', array($this, 'webhook_ipn')); #addOrder URL #http://domain.com/wc-api/cs_stripe_ipn/
		add_action('woocommerce_api_cs_stripe_addorder', array($this, 'add_custom_order')); #addOrder URL #http://localhost/wpsite/?wc-api=cs_stripe_addorder
		add_action('woocommerce_api_cs_stripe_failed', array($this, 'process_failed')); #addOrder URL #http://domain.com/wc-api/wc_shieldpp_ipn/
	
		add_action( 'woocommerce_api_wc_gateway_paypal', array( $this, 'check_ipn_response' ) );

	

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
		$this->id                 = 'cs_stripe';
		$this->icon               = apply_filters('woocommerce_stripspp_icon', '');
		$this->method_title       = __('CS Stripes', 'woocommerce');
		$this->method_description = __('This plugin Stripes Payment Gateway Protected by CashShield System.', 'woocommerce');
		$this->has_fields         = true;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'            => array(
				'title'       => __('Enable/Disable', 'woocommerce'),
				'label'       => __('Enable CS Stripe Payment Gateway', 'woocommerce'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'title'              => array(
				'title'       => __('Stripes', 'woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
				'default'     => __('Stripes', 'woocommerce'),
				'desc_tip'    => true,
			),
			'client_id'=> array(
				'title'       => __('Stripe ClientID', 'woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Stripe ClientID.', 'woocommerce'),
				'default'     => __('', 'woocommerce'),
				'desc_tip'    => true,
			),
			'secret'=> array(
				'title'       => __('Stripe Secret', 'woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Stripe Secret', 'woocommerce'),
				'default'     => __('', 'woocommerce'),
				'desc_tip'    => true,
			),
			'endpoint_secret'=> array(
				'title'       => __('Stripe Endpoint Secret', 'woocommerce'),
				'type'        => 'safe_text',
				'description' => __('Stripe Endpoint Secret.', 'woocommerce'),
				'default'     => __('', 'woocommerce'),
				'desc_tip'    => true,
			),
			'type'   => array(
				'title'       => __('Stripes Type', 'woocommerce-gateway-cs-tripes'),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __('Select Stripes Type', 'woocommerce-gateway-cs-tripes'),
				'default'     => 'direct',
				'options'     => array(
					'direct'       => 'Direct',
					'checkout'     => 'Checkout',
					'paymentlink'  => 'Paymentlink'
				),
			),
			/*
			'cardtypes'   => array(
				'title'       => __('Accepted Cards', 'woocommerce-gateway-cs-tripes'),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'description' => __('Select which card types to accept.', 'woocommerce-gateway-cs-tripes'),
				'default'     => 'all',
				'options'     => array(
					'MasterCard'       => 'MasterCard',
					'Visa'             => 'Visa',
					'Discover'         => 'Discover',
					'American Express' => 'American Express',
				),
			),*/
			'description'        => array(
				'title'       => __('Description', 'woocommerce'),
				'type'        => 'textarea',
				'description' => __('Payment Stripe protected buy Cashshield.', 'woocommerce'),
				'desc_tip'    => true,
			),
			'testmode' => array(
                'title'       => __('Test Mode', 'wp_shieldpp'),
                'type'        => 'checkbox',
                'label'       => __('Enable / Disable', 'wp_shieldpp'),
                'default'     => 'true',
                'description' => __('Enable / Disable test mode.', 'wp_shieldpp')
            )
			
		);
	}



	/**
	 * UI - Payment page fields for Inspire Commerce.
	 */
	function payment_fields()
	{
		
		if($this->stripes_type == "paymentlink")
		{ ?>
			<p>Check out with Stripes PayLink</p>
		<?php 
		}
		if($this->stripes_type == "checkout")
		{ ?>
			<p>Check out with Stripes</p>
		<?php 
		}
		
		if($this->stripes_type == "direct")
		{ 
			if ($this->description) {
		?>
				<p><?php echo $this->description; ?></p>
				<?php } ?>
				<fieldset style="padding-left: 40px;">
					<!-- Show input boxes for new data -->
					<div id="cs-stripes-cardform-new-info">
						<!-- Credit card number -->
						<p class="form-row form-row-first">
							<label for="ccnum"><?php echo __('Credit Card number', 'woocommerce-gateway-cs-stripes'); ?> <span class="required">*</span></label>
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
							<label for="cvv"><?php _e('Card Code(CVC)', 'woocommerce-gateway-cs-stripes'); ?> <span class="required">*</span></label>
							<input required type="text" class="input-text" id="cvv" name="cvv" maxlength="4" style="width:80px" />
							<span class="help"><?php _e('3 or 4 digits usually found on the signature strip.', 'woocommerce-gateway-cs-tripes'); ?></span>
						</p>
				</fieldset>
		<?php
		} 
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
		return  true; 
		$is_acitive = false;

		$shield_info = $this->getShieldInfo();

		if ($shield_info['status'] == true) {
			$shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
			$is_acitive = $shield_gateways['stripes']['active'];
		}
		return  $is_acitive;
	}

	private $shield_info = null;

	public function getShieldInfo()
	{
		// $shield_api = new Shield_API(
		// 	$this->api_url,
		// 	$this->merchant_key,
		// 	$this->shield_key
		// );
		// $shield_info = $shield_api->getShieldInfo();

		// return $shield_info;		
		$shieldInfo = get_option('wc_shieldpp_setting');

		$this->shield_info = [
			'status' => true,
			'result' => $shieldInfo,
		];

		return json_decode($shieldInfo, true);
	}

	public function getStripeSetting()
	{
		$shield_info = $this->getShieldInfo();
		$gateway_settings = array();
		if ($shield_info['status'] == true) {
			$shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
			$gateway_settings = $shield_gateways['stripes'];
		}
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
	 * Process the payment Stripe type Paylink.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */

	public function process_stripes_paylink($order_id)
	{
		$order = wc_get_order($order_id);
		
		$stripe = new \Stripe\StripeClient($this->stripes_secret);
		$price_plan = $stripe->prices->create([
		  'currency' => 'usd',
		  'unit_amount' => ceil($order->get_total()*100),
		  'product_data' => ['name' => 'Pay for service #'.$order_id],
		]);
		
		$pay_link_response = $stripe->paymentLinks->create([
		  'line_items' => [
				[
				  'price' => $price_plan->id,
				  'quantity' => 1,
				]
			],
		   'payment_intent_data'=>[
				'metadata'=>['order_id'=>$order_id]
		   ]
		]);
		
		$paylink = $pay_link_response->url;
		$transaction_id = $pay_link_response->id;
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
	
	public function process_stripes_checkout($order_id)
	{
		$order = wc_get_order($order_id);
		
		$stripe = new \Stripe\StripeClient($this->stripes_secret);
		$price_plan = $stripe->prices->create([
		  'currency' => 'usd',
		  'unit_amount' =>ceil($order->get_total()*100),
		  'product_data' => ['name' => 'Pay for service #'.$order_id],
		]);
		
		$checkout_response =  $stripe->checkout->sessions->create([
		  'success_url' => $order->get_checkout_order_received_url(),
		  'line_items' => [
			[
			  'price' =>$price_plan->id,
			  'quantity' => 1,
			],
		  ],
		  'mode' => 'payment',
		  'payment_intent_data'=>[
				'metadata'=>['order_id'=>$order_id]
		   ]
		]);

		
		$checkoutlink = $checkout_response->url;
		$transaction_id = $checkout_response->id;
		update_post_meta($order_id, 'cs_ref_code', $transaction_id);
		update_post_meta($order_id, 'cs_pp_payment_link', $checkoutlink);

		// Remove cart.
		WC()->cart->empty_cart();
		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $checkoutlink,
		);
	}
	
	
	
	function process_stripes_direct_old($order_id){
		$shield_info = $this->getShieldInfo();

		$stripes_settings = array();
		if ($shield_info['status'] == true) {
			$shield_gateways = json_decode($shield_info['result']['shield_gateways'], true);
			$stripes_settings = $shield_gateways['stripes']['settings'];
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
			'pmethod' => "STRIPE",
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

	
	
	function process_stripes_direct($order_id){
		$order = wc_get_order($order_id);
		$total = $order->get_total();
		
		$checkout_url = $order->get_checkout_order_received_url();
		$success_url = $order->get_checkout_order_received_url();
		
		$expmonth = $this->get_post('expmonth');
		$expyear  = '';
		if ($expmonth < 10) {
			$expmonth = '0' . $expmonth;
		}
		if ($this->get_post('expyear') != null) {
			$expyear = $this->get_post('expyear');
		}

		$cards = array(
			'ccnumber'  => $this->get_post('ccnum'),
			'cvv'       => $this->get_post('cvv'),
			'expmonth'     => $expmonth,
			'expyear'     => $expyear,
		);
		
		$stripe = new \Stripe\StripeClient($this->stripes_secret);
		
				
				
		// create card token
		$card_token = $stripe->tokens->create([
		  'card' => [
			'number' => $cards['ccnumber'],
			'exp_month' =>$cards['expmonth'],
			'exp_year' => $cards['expyear'],
			'cvc' =>$cards['cvv'],
		  ],
		]);
		if(!$card_token)
		{
			return array(
				'result'   => 'failed',
				'redirect' => $checkout_url,
			);
		}
		
		$pmethod = $stripe->paymentMethods->create([
		  'type' => 'card',
		  'card' => [
			'token'=>$card_token->id
		  ],
		  //'billing_details' => ['name' => 'John Doe'],
		]);
		
		if(!$pmethod)
		{
			return array(
				'result'   => 'failed',
				'redirect' => $checkout_url,
			);
		}


		$pay_intent =  $stripe->paymentIntents->create([
		  'amount' => ceil($order->get_total()*100),
		  'currency' => 'usd',
		  'metadata'=>['order_id'=>$order_id]
		]);


		if(!$pay_intent)
		{
			return array(
				'result'   => 'failed',
				'redirect' => $checkout_url,
			);
		}

		$capture_payment = $stripe->paymentIntents->confirm(
		  $pay_intent->id,
		  [
			'payment_method' =>$pmethod->id,
			'return_url'=>$success_url
		  ]
		);
		if(!$capture_payment)
		{
			return array(
				'result'   => 'failed',
				'redirect' => $checkout_url,
			);
		}
		
		$transaction_id = $capture_payment->id;
		update_post_meta($order_id, 'cs_ref_code', $transaction_id);
		update_post_meta($order_id, 'cs_pp_payment_link',"PAY DIRECT(".$capture_payment->id.")");
		// Handle the event
		switch ($capture_payment->status) {
			case 'succeeded':
				$this->handlePaymentSucceeded($capture_payment);
				// process sucess order
				return array(
					'result'   => 'success',
					'redirect' => $success_url,
				);
				file_put_contents($file_logs, "Successed: (".$capture_payment->metadata->order_id.")" .$capture_payment->id . PHP_EOL, FILE_APPEND);
				break;
			case 'canceled':
				$this->handlePaymentFailed($capture_payment);
				return array(
					'result'   => 'failed',
					'redirect' => $checkout_url,
				);
			default:
				return array(
					'result'   => 'failed',
					'redirect' => $checkout_url,
				);
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
		if($this->stripes_type == "paymentlink")
		{
			$paylink_process = $this->process_stripes_paylink($order_id);
			return $paylink_process;
		}
		if($this->stripes_type == "checkout")
		{
			$checkout_process = $this->process_stripes_checkout($order_id);
			return $checkout_process;
		}
		if($this->stripes_type == "direct")
		{
			$direct_process = $this->process_stripes_direct($order_id);
			return $direct_process;
		}
	}

	
	
	function stripeCustomOrderCheckout($order_id,$tranData)
	{
		try {
			$order = wc_get_order($order_id);
			$stripe_settings = $tranData['stripe_settings'];		
			
			$stripe = new \Stripe\StripeClient($stripe_settings['stripes_secret']);
			$price_plan = $stripe->prices->create([
			  'currency' => 'usd',
			  'unit_amount' =>ceil($order->get_total()*100),
			  'product_data' => ['name' => 'Pay for service #'.$order_id],
			]);
			
			$checkout_response =  $stripe->checkout->sessions->create([
			  'success_url' => $order->get_checkout_order_received_url(),
			  'line_items' => [
				[
				  'price' =>$price_plan->id,
				  'quantity' => 1,
				],
			  ],
			  'mode' => 'payment',
			  'payment_intent_data'=>[
					'metadata'=>['order_id'=>$order_id]
			   ]
			]);

			
			$checkoutlink = $checkout_response->url;
			$transaction_id = $checkout_response->id;
			update_post_meta($order_id, 'cs_ref_code', $transaction_id);
			update_post_meta($order_id, 'cs_pp_payment_link', $checkoutlink);
			
			$res   = [
					'status'=>'SUCCESS',
					'result' =>[
						'type' =>$stripe_settings['stripes_type'],
						'payment_code'=>"00",
						'shield_ref_code' => $order_id,
						'merchant_ref_code'=>$tranData['cs_ref_code'],                                
						'transaction_id' => $transaction_id,
						'payment_link'=> $checkoutlink,
					]
			];
			return $res;
		}catch (Exception $e) {
			$res=array('status'=>'ERORR','error'=>'Can not create checkout order:'.$e->getMessage());
		}
	}
	
	function stripeCustomOrderPaymentLink($order_id,$tranData)
	{
		try {
			$order = wc_get_order($order_id);
			$stripe_settings = $tranData['stripe_settings'];		
			
			$stripe = new \Stripe\StripeClient($stripe_settings['stripes_secret']);
			
			$price_plan = $stripe->prices->create([
			  'currency' => 'usd',
			  'unit_amount' => ceil($order->get_total()*100),
			  'product_data' => ['name' => 'Pay for service #'.$order_id],
			]);
			
			$pay_link_response = $stripe->paymentLinks->create([
			  'line_items' => [
					[
					  'price' => $price_plan->id,
					  'quantity' => 1,
					]
				],
			   'payment_intent_data'=>[
					'metadata'=>['order_id'=>$order_id]
			   ]
			]);
			
			$paylink = $pay_link_response->url;
			$transaction_id = $pay_link_response->id;
			update_post_meta($order_id, 'cs_ref_code', $transaction_id);
			update_post_meta($order_id, 'cs_pp_payment_link', $paylink);
			
			$res   = [
					'status'=>'SUCCESS',
					'result' =>[
						'type' =>$stripe_settings['stripes_type'],
						'payment_code'=>"00",
						'shield_ref_code' => $order_id,
						'merchant_ref_code'=>$tranData['cs_ref_code'],                                
						'transaction_id' => $transaction_id,
						'payment_link'=> $paylink,
					]
			];
			return $res;
		}catch (Exception $e) {
			$res=array('status'=>'ERORR','error'=>'Can not create paymentlink order:'.$e->getMessage());
		}
	}
	function stripeCustomOrderDirect($order_id,$tranData)
	{
		try {
			$stripe_settings = $tranData['stripe_settings'];	
			$card = $tranData['card'];
			if(!isset($card) || empty($card['number']) || empty($card['exp_month']) || empty($card['exp_year'])|| empty($card['cvc']))
			{
				return $res = [
					'status'=>'ERORR',
					'error'=>'CardInfo Invalid'
				];
			}
			$stripe = new \Stripe\StripeClient($stripe_settings['stripes_secret']);
			
			$order = wc_get_order($order_id);
			$total = $order->get_total();
			
			$checkout_url = $order->get_checkout_order_received_url();
			$success_url = $order->get_checkout_order_received_url();
			
			// create card token
			$card_token = $stripe->tokens->create([
			  'card' => [
				'number' => $card['number'],
				'exp_month' =>$card['exp_month'],
				'exp_year' => $card['exp_year'],
				'cvc' =>$card['cvc'],
			  ],
			]);
			if(!$card_token)
			{
				return $res = [
					'status'=>'ERORR',
					'error'=>'Can not create card token!'
				];
			}
			
			$pmethod = $stripe->paymentMethods->create([
			  'type' => 'card',
			  'card' => [
				'token'=>$card_token->id
			  ],
			  //'billing_details' => ['name' => 'John Doe'],
			]);
			
			if(!$pmethod)
			{
				return $res = [
					'status'=>'ERORR',
					'error'=>'Can not create payment method!'
				];
			}


			$pay_intent =  $stripe->paymentIntents->create([
			  'amount' => ceil($order->get_total()*100),
			  'currency' => 'usd',
			  'metadata'=>['order_id'=>$order_id]
			]);


			if(!$pay_intent)
			{
				return $res = [
					'status'=>'ERORR',
					'error'=>'Can not create payment intent!'
				];
			}

			$capturePayment = $stripe->paymentIntents->confirm(
			  $pay_intent->id,
			  [
				'payment_method' =>$pmethod->id,
				'return_url'=>$success_url
			  ]
			);
			if(!$capturePayment)
			{
				return $res = [
					'status'=>'ERORR',
					'error'=>'Can not make capture payment!'
				];
			}
			
			$transaction_id = $capturePayment->id;
			update_post_meta($order_id, 'cs_ref_code', $transaction_id);
			update_post_meta($order_id, 'cs_pp_payment_link',"PAY DIRECT(".$capturePayment->id.")");
			// Handle the event
			switch ($capturePayment->status) {
                case 'succeeded':
					$this->handlePaymentSucceeded($capturePayment);
                    $status = 'SUCCESS';
                    $paymentCode = "00";
                    break;
                case 'canceled':
					$this->handlePaymentFailed($capturePayment);
                    $status = 'FAILED';
                    $paymentCode = "01";
                    break;
                case 'requires_action':
                    $status = 'REQUIRES_ACTION';
                    $nextAction = $capturePayment->next_action;
                    $paymentCode = "02";
                    break;
                default:
                    $status = 'PENDING';
                    $paymentCode = "03";
                }
                $res   = [
                            'status' => $status,
                            'result' =>[
                                'type' => $stripe_settings['stripes_type'],
                                'payment_code'=>$paymentCode,
                                'shield_ref_code' => $order_id,
                                'merchant_ref_code'=>$tranData['cs_ref_code'],                                
                                'transaction_id' => $capturePayment->id,
                                'capture_status'=>$capturePayment->status,
                                'payment_link'=>null
                            ]
                        ];
                if(isset($nextAction))
                {
                    $res['result']['next_action']=$nextAction;
                }
			return $res;
		}catch (Exception $e) {
			$res=array('status'=>'ERORR','error'=>'Can not create direct order:'.$e->getMessage());
		}
	}
	public function add_custom_order()
	{
		// global $woocommerce,$wpdb;
		try {
			$tran_data = $_POST;
			$cs_ref_code = $_POST['cs_ref_code'];
			$product_price = $_POST['total_price'];
			$customer_email = $_POST['customer_email'];
			$product_name = $_POST['product_name'];
			$post_id = 0;
			$first_name = $_POST['customer_first_name'];
			$last_name =  $_POST['customer_last_name'];
			$mc_success_url = $_POST['mc_success_url'];
			$mc_failed_url = $_POST['mc_failed_url'];
			$stripe_settings = $_POST['stripe_settings'];
		
			if(!isset($stripe_settings) || empty($stripe_settings['stripes_client_id']) || empty($stripe_settings['stripes_secret']) || empty($stripe_settings['stripes_type']))
			{
				$res = array('status'=>'ERROR','error'=>'Invalid Stripe API.');
				ob_clean();
				echo json_encode($res);
				ob_end_flush();
				die;
			}
			
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
			$order->add_product($product, 1, $cart_item_data);
			$order->set_total($product_price);

			// add Gateway
			$payment_gateways = WC()->payment_gateways->payment_gateways();
			$order->set_payment_method($payment_gateways['cs_stripe']);


			$order->calculate_totals();
			WC()->session->order_awaiting_payment = $order->get_id();
			update_post_meta($order->get_id(), 'cs_ref_code', $cs_ref_code);
			update_post_meta($order->get_id(), 'mc_success_url', $mc_success_url);
			update_post_meta($order->get_id(), 'mc_failed_url', $mc_failed_url);

			// make paypal invoice here 
			$order_id = $order->get_id();
			
			if ($order_id) {
				
				switch($stripe_settings['stripes_type']){
					case 'checkout':
						$res = $this->stripeCustomOrderCheckout($order_id, $tran_data);
						break;
					case 'paymentlink':
						$res = $this->stripeCustomOrderPaymentLink($order_id, $tran_data);
						break;
					case 'direct':
						$res = $this->stripeCustomOrderDirect($order_id, $tran_data);
						break;
					default:
						$res = array('status' => 'ERROR', 'error' => 'Stripe type invalid');
						break;
				}
				
			}else{
				$res = array('status' => 'ERROR', 'error' => 'Can not create shield order !');
			}
		} catch (Exception $e) {
			$message = "Error:\n";
			$message .= "Message: " . $e->getMessage() . "\n";
			$message .= "File: " . $e->getFile() . "\n";
			$message .= "Line: " . $e->getLine() . "\n";

			$log_file = "Log";
			log_error($log_file, $message);
			telegram_push_log($message);
			$res=array('status'=>'ERORR','error'=>$message);
		}
		ob_clean();
		echo json_encode($res);
		ob_end_flush();
		die;
	}

	private function create_stripe_invoice($order_id = null, $tran_data = [])
	{
		$file_logs = plugin_dir_path(__FILE__) . "/logs/stripe_create_" . date("Y-m-d") . ".txt";
		$stripe_settings = $this->getStripeSetting();
		file_put_contents($file_logs, "Settings:" . json_encode($stripe_settings) . PHP_EOL, FILE_APPEND);

		$stripes_client_id = $stripe_settings['settings']['stripes_client_id'];
		$stripes_secret = $stripe_settings['settings']['stripes_secret'];
		

		$stripe = new \Stripe\StripeClient($this->stripes_secret);

		$cards = json_decode(str_replace('\\', "", $tran_data['cards']), true);

		$cc_number	=		$cards['ccnumber'];  //"4242424242424242";
		$exp_month	=		$cards['expmonth']; //"04";
		$exp_year	=		$cards['expyear']; //"2026";
		$cvc		=		$cards['cvv']; //"177";*/

		/*$cc_number	=		"4242424242424242";
		$exp_month	=		"04";
		$exp_year	=		"2026";
		$cvc		=		"177";*/

		$amount  = $tran_data['total_price'] * 100;
		$description = "Pay for " . $order_id;

		$token = null;
		try {
			$token = $stripe->tokens->create(
				array(
					'card' => array(
						'number' => $cc_number,
						'exp_month' => $exp_month,
						'exp_year' => $exp_year,
						'cvc' => $cvc,
					),
				)
			);
		} catch (Exception $e) {
			$message = "Error:\n";
			$message .= "Message: Error in create_stripe_invoice : " . $e->getMessage() . "\n";
			$message .= "File: " . $e->getFile() . "\n";
			$message .= "Line: " . $e->getLine() . "\n";

			log_error($file_logs, $message);
			telegram_push_log($message);
		}

		if ($token) {
			try {
				$charge = $stripe->charges->create(array(
					"amount" =>	$amount,
					"currency" => "eur",
					"source" => $token['id'],
					"description" => $description,
				));
				$file_logs = "logs/stripe_" . date("Y-m-d") . ".txt";
				file_put_contents($file_logs, "Value:" . json_encode($charge) . PHP_EOL, FILE_APPEND);

				$status 		= $charge->status;
				$description	= $charge->description;
				$transaction_id = $charge->id;
				$receipt_url = $charge->receipt_url;

				if ($charge->status == "succeeded" ?? null) {
					$res = array(
						'status' => true,
						'code' => "00",
						'msg' => 'Payment succeeded',
						'transaction_id' => $transaction_id ?? null,
						'receipt_url' => $receipt_url ?? null,
					);
				} else {
					$res = array(
						'status' => true,
						'code' => "01",
						'msg' => 'Payment Failed',
						'transaction_id' => $transaction_id ?? null,
						'receipt_url' => $receipt_url ?? null,
					);
				}
			} catch (Exception $e) {
				$message = "Error:\n";
				$message .= "Message: Error in create_stripe_invoice (token) : " . $e->getMessage() . "\n";
				$message .= "File: " . $e->getFile() . "\n";
				$message .= "Line: " . $e->getLine() . "\n";

				log_error($file_logs, $message);
				telegram_push_log($message);
			}
		} else {
			$res = array('status' => false, 'msg' => "Can not create payment. Please contact admin.");
			file_put_contents($file_logs, "ERROR(3):" . "Can not create payment. Please contact admin" . PHP_EOL, FILE_APPEND);
		}
		return $res;
	}

	
	public function handlePaymentSucceeded($paymentIntent){
		$order_id = $paymentIntent->metadata->order_id;
		$order = wc_get_order($order_id);
		$transaction_id = $paymentIntent->id;
		$cs_ref_code = "";
		$order->add_order_note(sprintf(__('Payment success (Trans ID: %s)', 'CS-Stripe'),$transaction_id));
		$order->add_order_note(sprintf(__('Payment success (MC ID: %s)', 'CS-Stripe'), $cs_ref_code));
		$order->payment_complete($transaction_id);
	}
	
	public function handlePaymentFailed($paymentIntent){
		$order_id = $paymentIntent->metadata->order_id;
		$order = wc_get_order($order_id);
		$transaction_id = $paymentIntent->id;
		$cs_ref_code = "";
		$order->add_order_note(sprintf(__('Payment Failed (Trans ID: %s)', 'CS-Stripe'),$transaction_id));
		$order->add_order_note(sprintf(__('Payment Failed (MC ID: %s)', 'CS-Stripe'), $cs_ref_code));
		$order->update_status('failed');
	}
	
	
	function webhook_ipn()
	{
		$payload = @file_get_contents('php://input');
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

		$stripe = new \Stripe\StripeClient($this->stripes_secret);

		$file_logs = plugin_dir_path(__FILE__)."/logs/ipn_" . date("Y-m-d") . ".txt";
		$callback_data = $_POST;
		file_put_contents($file_logs, "POSTBACK IPN:" . json_encode($callback_data) . PHP_EOL, FILE_APPEND);
		file_put_contents($file_logs, "payload IPN:" .$payload . PHP_EOL, FILE_APPEND);
		file_put_contents($file_logs, "sig_header IPN:" .$sig_header . PHP_EOL, FILE_APPEND);
		
		$event = null;

		try {
			$event = \Stripe\Webhook::constructEvent(
				$payload, $sig_header, $this->stripes_endpoint_secret
			);
			
		} catch(\UnexpectedValueException $e) {
			// Invalid payload
			  http_response_code(400);
			  echo json_encode(['Error parsing payload: ' => $e->getMessage()]);
			  exit();
		} catch(\Stripe\Exception\SignatureVerificationException $e) {
			// Invalid signature
			http_response_code(400);
			echo json_encode(['Error verifying webhook signature: ' => $e->getMessage()]);
			exit();
		}
		
		// Handle the event
		switch ($event->type) {
			case 'payment_intent.succeeded':
				$paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
				$this->handlePaymentSucceeded($paymentIntent);
				file_put_contents($file_logs, "Successed: (".$paymentIntent->metadata->order_id.")" .$paymentIntent->data->id . PHP_EOL, FILE_APPEND);
				break;
			case 'payment_intent.canceled':
				$paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
				$this->handlePaymentFailed($paymentIntent);		
				file_put_contents($file_logs, "canceled:" .$paymentIntent->data->id . PHP_EOL, FILE_APPEND);
				break;
			case 'payment_intent.payment_failed':
				$paymentIntent = $event->data->object; // contains a \Stripe\PaymentIntent
				$this->handlePaymentFailed($paymentIntent);
				file_put_contents($file_logs, "payment_failed:" .$paymentIntent->data->id . PHP_EOL, FILE_APPEND);
				break;
			default:
				echo 'Received unknown event type ' . $event->type;
		}
		http_response_code(200);
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
}