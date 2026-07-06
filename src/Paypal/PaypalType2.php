<?php
namespace Dell\WpShieldpp\Paypal;

//v2
use Dell\WpShieldpp\Helper\ClientIpHelper;
use Exception;
use PayPalHttp\HttpException;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Payments\CapturesGetRequest;


class PaypalType2
{
    public function __construct(
        private ProxyPayPalHttpClient $client,
        private $path,
        private $invoiceIdPrefix,
        private $ip_secret,
    ) {
    }

    public function createOrder($order, $cs_ref_code, $customer_ip)
    {
        $order_id = $order->get_id();
        $total = $order->get_total();
        $siteUrl = site_url();
        $cancel_url = $siteUrl . '/wc-api/wc_cancel/';

        $subnet = ClientIpHelper::getIpSubnet($customer_ip);
        $md5 = md5($order_id . $subnet);
        $sign = hash_hmac('sha256', $md5, $this->ip_secret);
        $returnUrl = $this->path;
        if (empty($returnUrl)) {
            $returnUrl =
                $siteUrl . '/wc-api/wc_return_url?orderId=' . $order_id . '&csRefCode=' . $cs_ref_code;
        }
        $returnUrl .= "&sign={$sign}";

        try {
            $client = $this->client;
            $unique_invoice_id = $order_id . '-' . time();
            $unique_invoice_id = $this->invoiceIdPrefix . $order_id;

            $request = new OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = [
                "intent" => "CAPTURE",
                "purchase_units" => [
                    [
                        "reference_id" => $order_id,
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => $total
                        ],
                        "invoice_id" => $unique_invoice_id
                    ]
                ],
                "application_context" => [
                    "cancel_url" => $cancel_url,
                    "return_url" => $returnUrl
                ]
            ];
            $response = $client->execute($request);

            $approvalLink = null;
            foreach ($response->result->links as $link) {
                if ($link->rel === 'approve') {
                    $approvalLink = $link->href;
                    break;
                }
            }

            update_post_meta($order_id, 'paypal_invoice_id', $unique_invoice_id);
            update_post_meta($order_id, 'cs_pp_payment_link', $approvalLink);

            return [
                'status' => true,
                'payment_link' => $approvalLink,
                'qr_code' => '',
                'cs_ref_code' => $cs_ref_code
            ];
        } catch (Exception $e) {
            $message = "Error:\n";
            $message .= "Message: Error in paypal_type_2 " . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";

            plugin_custom_log($message, 'debug.log');
            telegram_push_log($message);

            return [
                'status' => false,
                'msg' => $e->getMessage()
            ];
        }
    }
}