<?php
namespace Dell\WpShieldpp\Service;

use Dell\WpShieldpp\Helper\ClientIpHelper;
use Dell\WpShieldpp\Library\CsPluginConfig;

class IpSignService
{
  public static function genIpSign($ip, $orderId)
  {
    $ipSecret = CsPluginConfig::get('plugin.secret');
    $subnet = ClientIpHelper::getIpSubnet($ip);
    $str = $orderId . "|" . $subnet;
    $sign = hash_hmac('sha256', $str, $ipSecret);
    return $sign;
  }
}