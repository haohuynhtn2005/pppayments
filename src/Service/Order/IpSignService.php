<?php
namespace ShieldPpPayment\Service\Order;

use ShieldPpPayment\Helper\ClientIpHelper;
use ShieldPpPayment\Library\CsPluginConfig;

class IpSignService
{
  public static function genIpSign($ip, $orderId)
  {
    $ipSecret = CsPluginConfig::get('plugin.secret');
    $subnet   = ClientIpHelper::getIpSubnet($ip);
    $str      = $orderId . "|" . $subnet;
    $sign     = hash_hmac('sha256', $str, $ipSecret);
    return $sign;
  }
}