<html>
    <head>
        <title>Redirect to payment link</title>
    </head>

    <body>
        <div style="width: 100%; text-align: center">
            <p>
                <img
                    src="<?= plugin_dir_url(__FILE__) . '../assets/images/loading_spinner_large.gif' ?>"
                    style="width: 50px"
                />
            </p>
            <p>Waiting for redirection to the payment link.</p>
            <p>
                <a
                    href="<?= $paymentLink ?>"
                    title="Payment link"
                    style="font-weight: bold; color: red"
                >
                    Click here
                </a>
                if the system does not redirect automatically.
            </p>
        </div>

        <div
            id="paypal-button"
            style="
                display: flex;
                justify-content: center;
            "
        >
        </div>
        <script>
            class PageConfig {
                static token = '<?= $token ?>';
                static paymentLink = '<?= $paymentLink ?>';
                static captureUrl = '<?= $captureUrl ?>';
            }
        </script>
        <script
            src="https://www.paypal.com/sdk/js?client-id=<?= $clientId ?>">
        </script>
        <?php
            $file = '../assets/js/redirect-page.js';
            $filePath = plugin_dir_path(__FILE__) . $file;
            $fileTime = filemtime($filePath);
            $fileUrl = plugin_dir_url(__FILE__) . "$file?v=$fileTime";
        ?>
        <script
            src="<?= $fileUrl ?>"
        >
        </script>
    </body>
</html>
