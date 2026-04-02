<?php

namespace Dell\WpShieldpp;

class ConfigFormField
{

  public static function getFormFields()
  {
    $formFields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'ttr_shield_payments'),
        'type' => 'checkbox',
        'label' => __('Enable Paypal Payment', 'ttr_shield_payments'),
        'default' => 'yes'
      ),
      'business' => array(
        'title' => __('Paypal Email', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Business Email address', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),
      'API' => array(
        'title' => __('API', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Enter API Key', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),
      'Secret' => array(
        'title' => __('Secret', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Enter Secret Key', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),
      'SOAP_API' => array(
        'title' => __('API User', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Enter API User', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),
      'SOAP_PASS' => array(
        'title' => __('API Password', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Enter API Password', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),
      'SOAP_Signature' => array(
        'title' => __('Signature', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Enter Signature', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),
      'path' => array(
        'title' => __('Return URL', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Enter Return URL', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),

      'get_version' => array(
        'title' => __('Paypal Version', 'ttr_shield_payments'),
        'type' => 'multiselect',
        'description' => __('Please enter the security key', 'ttr_shield_payments'),
        'options' => array(
          'v1' => __('Version 1', 'woocommerce'),
          'v2' => __('Version 2', 'woocommerce'),
          // 'invoice' => __('Invoice', 'woocommerce' ),
          // 'invoice2' => __('Invoice v2', 'woocommerce' ),
          'web' => __('Web', 'woocommerce'),
        ),
        'css' => 'width:400px'
      ),

      'waitmess' => array(
        'title' => __('Waiting Message', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Insert waiting message', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),

      'completedmess' => array(
        'title' => __('Completed Message', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Insert completed message', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width:400px'
      ),

      'contact_page_link' => array(
        'title' => __('Contact page', 'ttr_shield_payments'),
        'type' => 'text',
        'description' => __('Automatic get contact page', 'ttr_shield_payments'),
        'default' => '/wc-api/contact',
        'css' => 'width:400px',
        // 'disabled' => f,
      ),

      'debug' => array(
        'title' => __('Debug Log', 'ttr_shield_payments'),
        'type' => 'checkbox',
        'label' => __('Enable logging', 'ttr_shield_payments'),
        'default' => 'true',
        'description' => __('Log events, such as trade status.', 'ttr_shield_payments')
      ),

      'testmode' => array(
        'title' => __('Test Mode', 'ttr_shield_payments'),
        'type' => 'checkbox',
        'label' => __('Enable / Disable', 'ttr_shield_payments'),
        'default' => 'true',
        'description' => __('Enable / Disable test mode.', 'ttr_shield_payments')
      ),
      ...self::getProxyFields()
    );

    return $formFields;
  }

  private static function getProxyFields()
  {
    $data = [
      // 'proxy_enabled' => array(
      //   'title' => __('Enable proxy ', 'ttr_shield_payments'),
      //   'type' => 'checkbox',
      //   'label' => __('Enable proxy', 'ttr_shield_payments'),
      //   'default' => 'no'
      // ),
      'proxy_type' => array(
        'title' => __('Proxy Type', 'ttr_shield_payments'),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'description' => __('Select the proxy protocol type for cURL requests.', 'ttr_shield_payments'),
        'default' => 'CURLPROXY_HTTP',
        'options' => array(
          '' => __('&nbsp;', 'ttr_shield_payments'),
          'default' => __('Default', 'ttr_shield_payments'),
          'CURLPROXY_HTTP' => __('HTTP Proxy', 'ttr_shield_payments'),
          'CURLPROXY_HTTPS' => __('HTTPS Proxy', 'ttr_shield_payments'),
          // 'CURLPROXY_HTTPS2' => __('HTTPS Proxy (HTTP/2)', 'ttr_shield_payments'),
          'CURLPROXY_HTTP_1_0' => __('HTTP 1.0 Proxy', 'ttr_shield_payments'),
          'CURLPROXY_SOCKS4' => __('SOCKS4 Proxy', 'ttr_shield_payments'),
          'CURLPROXY_SOCKS4A' => __('SOCKS4a Proxy (proxy resolves hostname)', 'ttr_shield_payments'),
          'CURLPROXY_SOCKS5' => __('SOCKS5 Proxy', 'ttr_shield_payments'),
          'CURLPROXY_SOCKS5_HOSTNAME' => __('SOCKS5 Proxy (proxy resolves hostname)', 'ttr_shield_payments'),
        ),
        // 'desc_tip'    => true,
      ),
      'proxy_info' => array(
        'title' => __('Proxy info', 'ttr_shield_payments'),
        'type' => 'textarea',
        // 'description' => __('Enter proxy as host:port or host:port:username:password', 'ttr_shield_payments'),
        'description' => __('Enter proxy as host:port or username:password@host:port', 'ttr_shield_payments'),
        // 'placeholder' => __('e.g., 127.0.0.1:8080 or 127.0.0.1:8080:user:pass', 'ttr_shield_payments'),
        'default' => '',
        'css' => 'width: 400px;',
        // 'desc_tip'    => true,
      ),
      // 'proxy_host' => array(
      //   'title' => __('Proxy Host', 'ttr_shield_payments'),
      //   'type' => 'text',
      //   'description' => __('Enter proxy host or IP', 'ttr_shield_payments'),
      //   'default' => '',
      //   // 'desc_tip'    => true,
      // ),
      // 'proxy_port' => array(
      //   'title' => __('Proxy Port', 'ttr_shield_payments'),
      //   'type' => 'number',
      //   'description' => __('Enter proxy port', 'ttr_shield_payments'),
      //   'default' => '',
      //   // 'desc_tip'    => true,
      // ),
      // 'proxy_auth_type' => array(
      //   'title' => __('Proxy Auth Type', 'ttr_shield_payments'),
      //   'type' => 'multiselect',
      //   'class' => 'wc-enhanced-select',
      //   'type' => 'select',

      //   'description' => __('Select the HTTP proxy authentication method', 'ttr_shield_payments'),
      //   'default' => array('CURLAUTH_BASIC'),
      //   'options' => array(
      //     // 'CURLAUTH_NONE' => __('None', 'ttr_shield_payments'),
      //     // 'CURLAUTH_BASIC' => __('Basic', 'ttr_shield_payments'),
      //     // 'CURLAUTH_DIGEST' => __('Digest', 'ttr_shield_payments'),
      //     // 'CURLAUTH_DIGEST_IE' => __('Digest IE', 'ttr_shield_payments'),
      //     // 'CURLAUTH_NEGOTIATE' => __('Negotiate (SPNEGO/Kerberos)', 'ttr_shield_payments'),
      //     // 'CURLAUTH_NTLM' => __('NTLM', 'ttr_shield_payments'),
      //     // 'CURLAUTH_NTLM_WB' => __('NTLM_WB (deprecated/rare)', 'ttr_shield_payments'),
      //     // 'CURLAUTH_BEARER' => __('Bearer', 'ttr_shield_payments'),
      //     // 'CURLAUTH_ANY' => __('Any (auto detect)', 'ttr_shield_payments'),
      //     // 'CURLAUTH_ANYSAFE' => __('Any Safe (exclude Basic)', 'ttr_shield_payments'),
      //     // 'CURLAUTH_ONLY' => __('Only (bitmask modifier, advanced)', 'ttr_shield_payments'),
      //     // 'CURLAUTH_AWS_SIGV4' => __('AWS SigV4', 'ttr_shield_payments'),

      //     '' => __('&nbsp;', 'ttr_shield_payments'),
      //     'default' => __('Default', 'ttr_shield_payments'),
      //     'CURLAUTH_BASIC' => __('Basic', 'ttr_shield_payments'),
      //     'CURLAUTH_NTLM' => __('NTLM', 'ttr_shield_payments'),
      //   ),
      //   // 'desc_tip' => true,
      // ),
      // 'proxy_username' => array(
      //   'title' => __('Proxy Username', 'ttr_shield_payments'),
      //   'type' => 'text',
      //   'description' => __('Enter proxy username for authentication', 'ttr_shield_payments'),
      //   'default' => '',
      //   // 'desc_tip'    => true,
      // ),
      // 'proxy_password' => array(
      //   'title' => __('Proxy Password', 'ttr_shield_payments'),
      //   'type' => 'text',
      //   'description' => __('Enter proxy password for authentication', 'ttr_shield_payments'),
      //   'default' => '',
      //   // 'desc_tip'    => true,
      // ),
    ];

    return $data;
  }

}