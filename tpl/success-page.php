<!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Success</title>
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) ?>../assets/css/style.css" />
    </head>

    <body>

        <div class="container">
            <h1>Payment Successful</h1>
            <p>Thank you for your payment. Your transaction has been completed.</p>
            <p class="italic fs-12">Please check your <strong>messages, email inbox, and spam folder</strong> for confirmation.</p>
            <p class="bold" style="color: red">DO NOT OPEN A DISPUTE/CHARGEBACK</p>
            <p>if you have any questions or concerns. Please <a href="<?php echo $this->contact_page_link ?>" style="color: #4299e1">contact</a> to get help!</p>
            <p><img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/loading_spinner_large.gif' ?>" style="width: 50px" /></p>
            <p class="redirect">You will be redirected shortly...</p>
            <p class="italic fs-12"><a href="<?php echo $redirectUrl ?>" title="Success link" style="font-weight: bold; color: red">Click here</a>
                if the system does not redirect automatically.</p>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = '<?php echo $redirectUrl ?>';
            }, 10000); // 5000ms
        </script>
    </body>
    </html>