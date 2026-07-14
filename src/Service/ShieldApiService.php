<?php
namespace Dell\WpShieldpp\Service;

class ShieldApiService
{
    protected $merchant_key = '';
    protected $shield_key = '';
    protected $API_URL = '';

    public function __construct($API_URL, $merchant_key, $shield_key)
    {
        $this->merchant_key = $merchant_key;
        $this->shield_key = $shield_key;
        $this->API_URL = $API_URL;
    }

    public function sendIpnWebhook($data = array(), $timeout = 60)
    {
        $url = $this->API_URL . '/transaction/webhooks';
        $postvars = http_build_query($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Merchant-Key:' . $this->merchant_key,
            'Shield-Key:' . $this->shield_key,
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $msg = "API URL: $url \n";
        $msg .= "merchant_key: {$this->merchant_key} - shield_key: {$this->shield_key}\n";

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = $response === false ? curl_error($ch) : null;
        if ($response === false || $httpcode != 200) {
            if ($curlErr) {
                $msg .= $curlErr;
            }
            plugin_custom_log($msg);
            telegram_push_log($msg);
        }

        $data = json_decode($response, true);
        telegram_push_log("{$msg}\n" . print_r($data, true));
        return $data;
    }

    /**
     * Create order
     * Dont not use in this site
     */
    public function createOrder($fields = array(), $timeout = 60)
    {
        $url = $this->API_URL . '/transaction/create';
        $postvars = http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Merchant-Key:' . $this->merchant_key,
            'Shield-Key:' . $this->shield_key,
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        if ($response === false) {
            $msg = "Exception: " . curl_error($ch);
            plugin_custom_log($msg);
        }

        $data = json_decode($response, true);
        return $data;
    }

    /**
     * Get shield Info From Api cashield
     * 
     * not used
     * 
     * @since 23/09/24
     */
    public function getShieldInfo($fields = array(), $timeout = 120)
    {
        $url = $this->API_URL . '/transaction/shieldsetting';
        $postvars = http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Merchant-Key:' . $this->merchant_key,
            'Shield-Key:' . $this->shield_key,
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);

        if ($response === false) {
            $msg = "Exception: " . curl_error($ch);
            plugin_custom_log($msg);
            telegram_push_log($msg);
        }

        $data = json_decode($response, true);
        return $data;
    }
}