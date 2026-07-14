<?php
namespace Dell\WpShieldpp\Paypal;

//v2
use Dell\WpShieldpp\Helper\ClientIpHelper;
use Exception;
use PayPalHttp\HttpException;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;


class PaypalClient
{

  public function getPaypalClientV2(
    PpaymentsPluginConfigDto $pluginConfig,
  ) {
    $clientId = $pluginConfig->clientId;
    $secret = $pluginConfig->secret;
    if ('yes' == $pluginConfig->testMode) {
      $environment = new SandboxEnvironment($clientId, $secret);
    } else {
      $environment = new ProductionEnvironment($clientId, $secret);
    }
    $client = new ProxyPayPalHttpClient($environment);
    $type = $pluginConfig->getOption('proxy_type');
    // $host = $pluginConfig->getOption('proxy_host');
    // $port = $pluginConfig->getOption('proxy_port');
    // $authType = $pluginConfig->getOption('proxy_auth_type');
    // $user = $pluginConfig->getOption('proxy_username');
    // $passwd = $pluginConfig->getOption('proxy_password');
    // $proxyDto = new ProxyConfigDto($type, $host, $port, $authType, $user, $passwd);
    $proxyInfo = $pluginConfig->getOption('proxy_info');
    $proxyDto = ProxyConfigDto::fromInfoStr($type, $proxyInfo);
    $client->setProxy($proxyDto);

    return $client;
  }
}