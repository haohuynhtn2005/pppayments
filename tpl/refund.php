<?php

/**
 * Cart errors page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/cart-errors.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined('ABSPATH') || exit;
?>

<div class="message-body">
    <div class="message-title" id="message-title">Your payment is pending</div>
    <div class="message-content" id="message-body">
        <img src="<?= $assetUrl ?>/loading.gif" />
        <div style="margin-top:20px">
		
			We cant process your payment, because it is suggestion from Papyal.<br>
			The delay can be 24 hours, and released at <b><?php echo date("Y-m-d H:i:s T", strtotime("+24 hours")) ?></b>
		
		</div>
    </div>
</div>
<style>
    .message-body {
		padding:5% 0;
        width: 600px;
        max-width: 100%;
        margin: 0 auto;
    }

    .message-title {
        text-align: center;
        font-weight: 500;
    }

    .message-content {
        text-align: center;
        margin-top: 40px;
    }
</style>