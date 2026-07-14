<?php
namespace Dell\WpShieldpp\Paypal;

use Closure;

final class PpaymentsPluginConfigDto
{
  public function __construct(
    public $path,
    public $apiVersion,
    public $debug,
    public $testMode,
    public $business,
    public $clientId,
    public $secret,
    public $soapApi,
    public $soapPass,
    public $soapSignature,
    public $waitMessage,
    public $completedMessage,
    // public $proxyType,
    // public $proxyInfo,
    public Closure $getOptionCb,
  ) {
  }

  public function getOption(string $key, $empty_value = null)
  {
    return ($this->getOptionCb)(...\func_get_args());
  }

  public static function fromOptions(callable $getOption): self
  {
    $version = $getOption('get_version');

    $business = $getOption('business');
    $clientId = $getOption('API');
    $secret = $getOption('Secret');
    $soapApi = $getOption('SOAP_API');
    $soapPass = $getOption('SOAP_PASS');
    $soapSignature = $getOption('SOAP_Signature');
    $testMode = $getOption('testmode');

    if ($testMode === 'yes') {
      $business = 'sb-2yix216053012@business.example.com';
      $clientId = 'AQ3VGF0YRugRrryvM4vRKb2ZnAnETjzLutbWC9-sI4A6UyfNGs8pnV3gI5POJDQ_O4ryZ7PotrqwQLCA';
      $secret = 'EDkwqnGDsB2VHD-KenTyWDZQaYjd0NH9n7GSvSMTRmXg_JG6bjJXB-y7QbN6M1wdgfQ1uuFCUCbk2NPH';

      $soapApi = 'sb-2yix216053012_api1.business.example.com';
      $soapPass = 'ZXEC5DAHMFSR4AUU';
      $soapSignature = 'AI9f6AX0wv.h1aOmx81yllsPg4PgAkj5Gy0X3rf7jWL3xzRGZ.kVQ6oE';
    }

    return new self(
      path: $getOption('path'),
      apiVersion: $version[0] ?? 'v2',
      debug: (bool) $getOption('debug'),
      testMode: $testMode,
      business: $business,
      clientId: $clientId,
      secret: $secret,
      soapApi: $soapApi,
      soapPass: $soapPass,
      soapSignature: $soapSignature,
      waitMessage: $getOption('waitmess'),
      completedMessage: $getOption('completedmess'),
      // proxyType: $getOption('proxy_type'),
      // proxyInfo: $getOption('proxy_info'),
      getOptionCb: Closure::fromCallable($getOption),
    );
  }


}