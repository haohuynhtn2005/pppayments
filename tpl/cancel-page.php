 <!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Cancelled</title>
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) ?>../assets/css/style.css" />
    </head>
    
    <body>
        <div class="container">
            <h1>Payment Cancelled</h1>
            <p>We're sorry, your payment has been cancelled.</p>
            <p class="bold">Please check your payment method and try again, or <a href="<?php echo $this->contact_page_link ?>" class="bold">contact</a> support if you need assistance.</p>
            <p><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/loading_spinner_large.gif' ?>" style="width: 50px" /></p>
            <p class="redirect">You will be redirected shortly...</p>
            <p class="italic fs-12"><a href="<?php echo $redirectUrl ?>" title="Success link" style="font-weight: bold; color: red">Click here</a>
                if the system does not redirect automatically.</p>
        </div>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = '<?php echo $redirectUrl ?>';
            }, 5000); // 5000ms
        </script>
    </body>

</html>