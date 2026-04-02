<?php
namespace Dell\WpShieldpp\Paypal;

class PaymentNvpService
{
  public function __construct(
    private string $endpoint,
    private string $apiUser,
    private string $apiPassword,
    private string $apiSignature,
  ) {
  }

  public function get_nvp_payment_status($txn_id)
  {
    $gateways = WC()->payment_gateways()->payment_gateways();
    $gateway = $gateways['pppayments'] ?? null;
    ### Check order status
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->endpoint);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    // Optional but often useful for SOCKS
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);


    // NVPRequest for submitting to server
    $nvpreq = http_build_query([
      'METHOD' => 'GetTransactionDetails',
      'TRANSACTIONID' => $txn_id,
      'VERSION' => '124',
      'PWD' => $this->apiPassword,
      'USER' => $this->apiUser,
      'SIGNATURE' => $this->apiSignature,
    ], '', '&');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
    $response = curl_exec($ch);

    //telegram_push_log("rsp2: ". print_r($response, true));

    $nvpResArray = $this->deformatNVP($response);
    curl_close($ch);

    $or_status = $nvpResArray['PAYMENTSTATUS'];
    return $or_status;
  }


  private function deformatNVP($nvpstr)
  {
    $intial = 0;
    $nvpArray = array();

    while (strlen($nvpstr)) {
      // postion of Key
      $keypos = strpos($nvpstr, '=');
      // position of value
      $valuepos = strpos($nvpstr, '&') ? strpos($nvpstr, '&') : strlen($nvpstr);

      /* getting the Key and Value values and storing in a Associative Array */
      $keyval = substr($nvpstr, $intial, $keypos);
      $valval = substr($nvpstr, $keypos + 1, $valuepos - $keypos - 1);
      // decoding the respose
      $nvpArray[urldecode($keyval)] = urldecode($valval);
      $nvpstr = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
    }
    return $nvpArray;
  }
}