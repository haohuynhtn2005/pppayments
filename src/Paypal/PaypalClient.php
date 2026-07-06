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


class PaypalClient
{

}