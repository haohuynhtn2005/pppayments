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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<style>
body{visibility:hidden}
.loading{visibility:visible;width:100%;text-align:center;position:absolute;top:15%;}

</style>
<title>Loading</title>
<div class="loading">
	<img src="https://i.imgur.com/Jm5XYBF.gif">
</div>

<div class="paypal-htmlform">
	<form action="https://www.<?= $url ?>/cgi-bin/webscr" method="post">
		<input type="hidden" name="cmd" value="_xclick">
		<input type="hidden" name="business" value="<?= $business_email ?>">
		<input type="hidden" name="item_name" value="<?= $site_title ?> - Invoice #<?= $order_id ?>">
		<input type="hidden" name="item_number" value="<?= $order_id ?>">
		<input type="hidden" name="currency_code" value="USD">
		<input type="hidden" name="amount" value="<?= $amount ?>">
		
		<input name="return" type="hidden" value="<?= $returnURL ?>">
		<input name="cancel_return" type="hidden" value="<?= $cancelURL ?>">
		<input name="notify_url" type="hidden" value="<?= $notifyURL ?>">
	
		<input type="submit" name="submit" id="paynow" value="Pay now">
	</form>
</div>


<script>
	$(document).ready(function(){
		setTimeout(function(){
			$("#paynow").click();
		}, 0);
		
		document.title = "Loading...";
	});  
</script>
