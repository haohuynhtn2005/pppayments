<?php
namespace Dell\WpShieldpp\Paypal;

final readonly class WCGatewayPpaymentsPluginConfigDto
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
    public $contactPageLink,
  ) {
  }

  public static function fromOptions(callable $getOption): self
  {
    $version = $getOption('get_version');

    return new self(
      path: $getOption('path'),
      apiVersion: $version[0] ?? 'v2',
      debug: (bool) $getOption('debug'),
      testMode: (bool) $getOption('testmode'),
      business: $getOption('business'),
      clientId: $getOption('API'),
      secret: $getOption('Secret'),
      soapApi: $getOption('SOAP_API'),
      soapPass: $getOption('SOAP_PASS'),
      soapSignature: $getOption('SOAP_Signature'),
      waitMessage: $getOption('waitmess'),
      completedMessage: $getOption('completedmess'),
      contactPageLink: $getOption('contact_page_link'),
    );
  }
}