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

<?php if ($load == 1)
{
	$text = "OPEN INVOICE";
?>
<script>
$(document).on('click', 'button', function(e){ 
    e.preventDefault(); 
    var url = '<?= $invoice_url ?>'; 
    window.open(url, '_blank');
	document.getElementById("check").submit();
});
</script>
<?php } else {
	$text = "CONFIRM PAYMENT";

?>
<script>
$(document).ready(function(){
    setTimeout(function(){
    //deferred onload
		document.getElementById("check").submit();
    }, 10000);
});
</script>
<?php } ?>
<style>
<?php if ($ipre): ?>

body{visibility:hidden}
.loading{visibility:visible;width:100%;text-align:center;position:absolute;top:15%;}

<?php endif; ?>
.invoice {padding:5% 0}
#paynow{border-radius:3px;transition:.3s;display:inline-block;text-align:center;white-space:nowrap;border:none;font-size:15px;line-height:1;padding:12px 24px}

</style>
<title>Loading</title>

<?  if ($ipre) { ?>


<div class="loading">
<?php 
// echo "<pre>";var_dump ($ipre);
?>
	<center>
		<h2>YOUR ORDER ID #<?= $ipre['tran_id'] ?></h2>
		<img src="data:image/png;base64, <?= $qr_code ?>" alt="Paypal Invoce" /><br>
		<small>Scan your invoice by QRcode reader</small><br><br>
		<p>
		================================
		</p>
		Or you can press the button to pay your invoice<br><br>
		
		<form method="POST" id="check">
			<input type="hidden" name="load" value="<?= $load ?>">
			<button id="paynow"><?= $text ?></button>
		</form>
	</center>
</div>



<?  } else { ?>


<div class="invoice">
	<center>
		<h2>YOUR ORDER ID #<?= $order_id ?></h2>
		<img src="data:image/png;base64, <?= $qr_code ?>" alt="Paypal Invoce" /><br>
		<small>Scan your invoice by QRcode reader</small><br><br>
		<p>
		================================
		</p>
		Or you can press the button to pay your invoice<br><br>
		
		<form method="POST" id="check">
			<input type="hidden" name="load" value="<?= $load ?>">
			<button id="paynow"><?= $text ?></button>
		</form>
	</center>
</div>
	
<?  }  ?>
