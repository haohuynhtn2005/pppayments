<html>
    <head>
        <title>Waiting for redirection to the payment link.</title>
    </head>

    <body>
        <div style="width: 100%; text-align: center">
            <p>
                <img
                    src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/loading_spinner_large.gif' ?>"
                    style="width: 50px"
                />
            </p>
            <p>Waiting for redirection to the payment link.</p>
            <p>
                <a
                    href="<?php echo $paymentLink ?>"
                    title="Payment link"
                    style="font-weight: bold; color: red"
                >
                    Click here
                </a>
                if the system does not redirect automatically.
            </p>
        </div>
        
        <script>
            window.location.href = '<?php echo $redirectUrl ?>';
        </script>
    </body>
</html>
