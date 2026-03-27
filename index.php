<?php
/*
 * Plugin Name: ttr_shieldpayments
 * Description: TTR shield payments Plugin
 * Version: 1.1.5
 * Plugin Release: 2026-01-06 10:30
 * Author: ttrpay.net
 */

/*
require $_SERVER['DOCUMENT_ROOT'] .'/wp-content/plugins/ppcp/update/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://ipremium.io/libs/plugin/update.json',
	__FILE__, //Full path to the main plugin file or functions.php.
	'ppcp/ppcp.php'
);
*/
defined('ABSPATH') || exit('You are not allowed to access this file directly.');
require_once plugin_dir_path(__FILE__) . '/helpers/commonHelper.php';

//add_action('plugins_loaded', 'shieldpp_gateway_init', 0);
add_action('woocommerce_loaded', 'ttr_shieldpayments_gateway_init');

function ttr_shieldpayments_gateway_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    load_plugin_textdomain('wlstar', false, dirname(plugin_basename(__FILE__)) . '/lang/');
   
    $classes = [
        'libs/class-updater.php',
        'WC_Gateway_pppayments.php',
        //'class-wc-cs-stripe.php',
        //'class-wc-jpay.php',
    ];

    foreach ($classes as $class_file) {
        $file_path = plugin_dir_path(__FILE__) . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }

    new My_Plugin_Updater(__FILE__, 'https://api.ttrpay.net/v1/wp-plugins/shieldpp.json');
    add_filter('woocommerce_payment_gateways', 'woocommerce_ttr_shield_payments_add_gateway');
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ttr_shield_payments_plugin_edit_link');
}
/**
 * Add the gateway to WooCommerce
 *
 * @access public
 * @param array $methods
 * @package WooCommerce/Classes/Payment
 * @return array
 */
function woocommerce_ttr_shield_payments_add_gateway($methods)
{
    $methods[] = 'WC_Gateway_pppayments';
    $methods[] = 'cs_stripe';
    $methods[] = 'CS_JPAY';
    return $methods;
}

function ttr_shield_payments_plugin_edit_link($links)
{
    return array_merge(
        array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=pppayments') . '">' . __('Setup', 'ShieldPP') . '</a>'
        ),
        $links
    );
}

// display the extra data in the order admin panel
function ttr_shield_payments_display_order_data_in_admin( $order ){  ?>
    <div class="order_data_column">
        <h4><?php _e( 'TTRPAY Details' ); ?></h4>
        <?php 
			$cs_ref_code = get_post_meta($order->get_id(), 'cs_ref_code', true );
			if(empty(trim($cs_ref_code))){
				echo '<p><strong>' . __( 'Direct Order' ) . '</strong></p>';
			}else{
				echo '<p><strong>' . __( 'TTRpay ref Code' ) . ' : </strong>' . get_post_meta($order->get_id(), 'cs_ref_code', true ). '</p>';
			}
            echo '<p><strong>' . __( 'TTRpay Payment Link' ) . ' : </strong>' . get_post_meta($order->get_id(), 'cs_pp_payment_link', true ) . '</p>';
            echo '<p><strong>' . __( 'merchant return url' ) . ' : </strong>' . get_post_meta($order->get_id(), 'mc_success_url', true ) . '</p>';
            echo '<p><strong>' . __( 'merchant cancel url' ) . ' : </strong>' . get_post_meta($order->get_id(), 'mc_failed_url', true ) . '</p>';
        ?>
    </div>
<?php }
add_action( 'woocommerce_admin_order_data_after_order_details', 'ttr_shield_payments_display_order_data_in_admin' );

function send_contact_form_to_telegram( WP_REST_Request $request )
{
    $params = $request->get_json_params();
    
    $subject = sanitize_text_field($params['subject'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $category = sanitize_text_field($params['category'] ?? '');
    $message = sanitize_textarea_field($params['message'] ?? '');
    $other = sanitize_textarea_field($params['other_contact'] ?? '');
    $clientIp = sanitize_textarea_field($params['clientIp'] ?? '');
    $referer = sanitize_textarea_field($params['referer'] ?? '');

    if ( empty($subject) || empty($email) || empty($category) || empty($message) ) {
        return new WP_REST_Response(['error' => 'All fields are required'], 400);
    }
    
    $category = pmCodeDecryptToLink($category);

    $botToken = "6124763967:AAHfARyeqRvonizo-9l-RgRULBeioI10GO0";
    $chatId = "-4763218406";

    $text = "📩 *New Contact Form Submission* \n\n"
          . "🔹 *Subject:* `$subject`\n"
          . "📧 *Email:* `$email`\n"
          . "📧 *Ip:* `$clientIp`\n"
          . "📧 *referer:* `$referer`\n"
          . "📧 *Other contact:* `$other`\n"
          . "📂 *Category:* `$category`\n"
          . "📝 *Message:* \n```$message```\n";
          //. "📝 *Client Info:* \n```$clientInfo```\n";

    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        "chat_id" => $chatId,
        "text" => $text,
        "parse_mode" => "Markdown"
    ];

    $response = wp_remote_post($url, [
        'body'    => $data,
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return new WP_REST_Response(['error' => 'Failed to send message'], 500);
    }

    return new WP_REST_Response(['success' => 'Message sent'], 200);
}

add_action('rest_api_init', function () {
    register_rest_route('contact/v1', '/send', [
        'methods'  => 'POST',
        'callback' => 'send_contact_form_to_telegram',
        'permission_callback' => '__return_true',
    ]);
});


// Vô hiệu hóa comment trên toàn bộ website
function disable_comments() {
    return false;
}
add_filter('comments_open', 'disable_comments', 20, 2);
add_filter('pings_open', 'disable_comments', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);

// Ẩn menu bình luận trong Admin
function remove_comments_menu() {
    remove_menu_page('edit-comments.php');
}
add_action('admin_menu', 'remove_comments_menu');

// Xóa widget bình luận trong dashboard
function remove_comments_dashboard() {
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}
add_action('wp_dashboard_setup', 'remove_comments_dashboard');
