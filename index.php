<?php
/*
 * Plugin Name: ttr_shieldpayments
 * Description: TTR shield payments Plugin
 * Version: 1.3.4
 * Plugin Release: 2026-07-15 15:55
 * Author: ttrpay.net
 */
if (!defined( 'ABSPATH' )) {
    exit('You are not allowed to access this file directly.');
}

require_once plugin_dir_path(__FILE__) . '/vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . '/helpers/commonHelper.php';
// require_once plugin_dir_path(__FILE__) . '/libs/shield_api.php';

use ShieldPpPayment\Module\BaseModule;

BaseModule::init();
