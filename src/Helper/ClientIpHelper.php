<?php
namespace Dell\WpShieldpp\Helper;

final class ClientIpHelper
{
  public static function getIpSubnet($ip)
  {
    $ip_parts = explode('.', $ip);
    $subnet = sprintf(
      '%s.%s.%s',
      $ip_parts[0] ?? '',
      $ip_parts[1] ?? '',
      $ip_parts[2] ?? ''
    );
    return $subnet;
  }

  public static function getClientIp()
  {
    // Get real visitor IP behind CloudFlare network
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
      $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $client = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote = $_SERVER['REMOTE_ADDR'];

    if (filter_var($client, FILTER_VALIDATE_IP)) {
      $ip = $client;
    } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
      $ip = $forward;
    } else {
      $ip = $remote;
    }
    return $ip;
  }
}