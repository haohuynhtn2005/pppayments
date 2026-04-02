<?php
namespace Dell\WpShieldpp\Paypal;

use CurlHandle;
use Dell\WpShieldpp\Helper\StrHelper;

class ProxyConfigDto
{
  public string $proxyType;
  public string $proxyHost;
  public null|int $proxyPort;
  public string $proxyUsername;
  public string $proxyPassword;
  public array $proxyAuthType;

  public function __construct(
    $proxyType = 'none',
    $proxyHost = '',
    $proxyPort = null,
    $proxyAuthType = [],
    $proxyUsername = '',
    $proxyPassword = '',
  ) {
    $this->proxyType = StrHelper::toString($proxyType);
    $this->proxyHost = StrHelper::toString($proxyHost);
    $this->proxyPort =
      $proxyPort !== '' && $proxyPort !== null ? (int) $proxyPort : null;
    $this->proxyUsername = StrHelper::toString($proxyUsername);
    $this->proxyPassword = StrHelper::toString($proxyPassword);
    $this->proxyAuthType = (array) $proxyAuthType;
  }

  public static function fromInfoStr($proxyType, $proxyInfo)
  {
    $proxyType = StrHelper::toString($proxyType);
    $proxyInfo = StrHelper::toString($proxyInfo);
    $host = null;
    $port = null;
    $user = null;
    $passwd = null;
    $auth = '';
    if (!empty($proxyInfo)) {
      $hostPort = $proxyInfo;
      if (strpos($proxyInfo, '@') !== false) {
        $parts = explode('@', $proxyInfo, 2);
        [$auth, $hostPort] = $parts;
        $authParts = explode(':', $auth, 2);
        $user = $authParts[0];
        $passwd = $authParts[1] ?? '';
      }

      // Split host and port
      if (strpos($hostPort, ':') !== false) {
        [$host, $port] = explode(':', $hostPort, 2);
        $port = (int) $port;
      } else {
        $host = $hostPort;
      }
    }

    $proxyDto = new self($proxyType, $host, $port, null, $user, $passwd);
    return $proxyDto;
  }

  /**
   * Check if proxy is enabled
   *
   * @return bool
   */
  public function isEnabled()
  {
    $proxyType = $this->getCurlProxyType();
    return $this->proxyHost !== '' && $proxyType !== null;
  }

  /**
   * Check if proxy auth is enabled
   *
   * @return bool
   */
  public function hasAuth()
  {
    return $this->proxyUsername !== '' && $this->proxyPassword !== '';
  }

  /**
   * Get proxy address as host[:port]
   *
   * @return string
   */
  public function getProxyAddress()
  {
    if (!$this->proxyPort) {
      return $this->proxyHost;
    }
    return $this->proxyHost . ':' . $this->proxyPort;
  }

  /**
   * Get proxy user pwd as user:pwd
   *
   * @return string
   */
  public function getProxyUserPwd()
  {
    $encodedUsername = rawurlencode($this->proxyUsername);
    $encodedPassword = rawurlencode($this->proxyPassword);
    $auth = <<<AUTH
    {$encodedUsername}:{$encodedPassword}
    AUTH;
    return $auth;
  }

  /**
   * Apply proxy settings to cURL handle
   *
   * @param resource|\CurlHandle $ch
   * @return void
   */
  public function applyToCurl(CurlHandle $ch)
  {
    if (false) {
      $this->applyToCurlIpTest($ch);
      $this->applyToCurlPaypalServerSdk($ch);
    }
    if (!$this->isEnabled()) {
      return;
    }

    $proxyAddress = $this->getProxyAddress();
    curl_setopt($ch, CURLOPT_PROXY, $proxyAddress);
    $proxyType = $this->getCurlProxyType();
    if ($proxyType !== null) {
      curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);
    }

    if ($this->hasAuth()) {
      $encodedUsername = rawurlencode($this->proxyUsername);
      $encodedPassword = rawurlencode($this->proxyPassword);
      curl_setopt($ch, CURLOPT_PROXYUSERNAME, $encodedUsername);
      curl_setopt($ch, CURLOPT_PROXYPASSWORD, $encodedPassword);
      // curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->getProxyUserPwd());

      curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
      $proxyAuth = $this->getCurlProxyAuthType();
      if ($proxyAuth !== null) {
        curl_setopt($ch, CURLOPT_PROXYAUTH, $proxyAuth);
      }
    }
  }

  /**
   * Get an array of cURL options for this proxy
   *
   * @return array<string,mixed>
   */
  public function getCurlOptions(): array
  {
    if (!$this->isEnabled()) {
      return [];
    }

    $options = [
      CURLOPT_PROXY => $this->getProxyAddress(),
    ];

    $proxyType = $this->getCurlProxyType();
    if ($proxyType !== null) {
      $options[CURLOPT_PROXYTYPE] = $proxyType;
    }

    if ($this->hasAuth()) {
      $encodedUsername = rawurlencode($this->proxyUsername);
      $encodedPassword = rawurlencode($this->proxyPassword);
      $options[CURLOPT_PROXYUSERNAME] = $encodedUsername;
      $options[CURLOPT_PROXYPASSWORD] = $encodedPassword;
      // $options[CURLOPT_PROXYUSERPWD] = $this->getProxyUserPwd();

      $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
      $proxyAuth = $this->getCurlProxyAuthType();
      if ($proxyAuth !== null) {
        $options[CURLOPT_PROXYAUTH] = $proxyAuth;
      }
    }

    return $options;
  }

  /**
   * Convert proxy type string to cURL constant value
   *
   * @return int|null
   */
  public function getCurlProxyType()
  {
    $map = [
      'none' => null,
      'CURLPROXY_HTTP' => CURLPROXY_HTTP,
      'CURLPROXY_HTTPS' => CURLPROXY_HTTPS,
      // 'CURLPROXY_HTTPS2' => CURLPROXY_HTTPS2,
      'CURLPROXY_HTTP_1_0' => CURLPROXY_HTTP_1_0,
      'CURLPROXY_SOCKS4' => CURLPROXY_SOCKS4,
      'CURLPROXY_SOCKS4A' => CURLPROXY_SOCKS4A,
      'CURLPROXY_SOCKS5' => CURLPROXY_SOCKS5,
      'CURLPROXY_SOCKS5_HOSTNAME' => CURLPROXY_SOCKS5_HOSTNAME,
    ];

    return $map[$this->proxyType] ?? null;
  }

  public function getProxyScheme(): string
  {
    $type = $this->proxyType;
    return match ($type) {
      'CURLPROXY_HTTP' => 'http',
      'CURLPROXY_HTTPS' => 'https',
      'CURLPROXY_SOCKS4' => 'socks4',
      'CURLPROXY_SOCKS4A' => 'socks4a',
      'CURLPROXY_SOCKS5' => 'socks5',
      'CURLPROXY_SOCKS5_HOSTNAME' => 'socks5h',
      default => 'http',
    };
  }

  /**
   * Convert proxy auth selection to CURLOPT_PROXYAUTH bitmask.
   *
   * @return int|null
   */
  private function getCurlProxyAuthType()
  {
    $selected = $this->normalizeProxyAuthSelection();
    if (empty($selected)) {
      return null;
    }

    $authMap = [
      // 'CURLAUTH_NONE' => null,
      // 'CURLAUTH_BASIC' => CURLAUTH_BASIC,
      // 'CURLAUTH_DIGEST' => CURLAUTH_DIGEST,
      // 'CURLAUTH_NEGOTIATE' => CURLAUTH_NEGOTIATE,
      // 'CURLAUTH_NTLM' => CURLAUTH_NTLM,
      // 'CURLAUTH_NTLM_WB' => CURLAUTH_NTLM_WB,
      // 'CURLAUTH_BEARER' => CURLAUTH_BEARER,
      // 'CURLAUTH_ANY' => CURLAUTH_ANY,
      // 'CURLAUTH_ANYSAFE' => CURLAUTH_ANYSAFE,

      'CURLAUTH_BASIC' => CURLAUTH_BASIC,
      'CURLAUTH_NTLM' => CURLAUTH_NTLM,
    ];

    $overideList = ['CURLAUTH_ANY', 'CURLAUTH_ANYSAFE', 'CURLAUTH_NONE'];
    foreach ($overideList as $val) {
      if (\in_array($val, $selected, true)) {
        return $authMap[$val] ?? null;
      }
    }

    $bitmask = 0;
    foreach ($selected as $key) {
      if (isset($authMap[$key])) {
        $bitmask |= $authMap[$key];
      }
    }
    if ($bitmask === 0) {
      return CURLAUTH_BASIC;
    }
    return $bitmask;
  }

  private function normalizeProxyAuthSelection()
  {
    $value = $this->proxyAuthType;
    if (\is_array($value)) {
      return array_map(function ($item) {
        return StrHelper::toString($item);
      }, $value);
    }

    if (!\is_string($value) || $value === '') {
      return [];
    }
    return [$value];
  }

  /**
   * Export safe array (without password)
   *
   * @return array
   */
  public function toArray()
  {
    return [
      'proxy_type' => $this->proxyType,
      'proxy_host' => $this->proxyHost,
      'proxy_port' => $this->proxyPort,
      'proxy_auth_type' => $this->proxyAuthType,
      'proxy_username' => $this->proxyUsername,
      // 'proxy_password' => $this->proxyPassword,
      'enabled' => $this->isEnabled(),
      'has_auth' => $this->hasAuth(),
    ];
  }

  private function applyToCurlPaypalServerSdk(CurlHandle $ch)
  {
    $json = <<<JSON
    {"port":0,"tunnel":false,"address":"socks5:\/\/1Jddr35mO3nJ322r:3MPnXtrJ0DGa1d9@94.103.59.95:46547","type":0,"auth":{"user":"\$this->user","pass":"\$this->pass","method":"1"}}
    JSON;
    $proxy = json_decode($json, true);
    curl_setopt_array($ch, [
      CURLOPT_PROXYTYPE => $proxy['type'],
      CURLOPT_PROXY => $proxy['address'],
      CURLOPT_PROXYPORT => $proxy['port'],
      CURLOPT_HTTPPROXYTUNNEL => $proxy['tunnel'],
      CURLOPT_PROXYAUTH => $proxy['auth']['method'],
      CURLOPT_PROXYUSERPWD =>
        $proxy['auth']['user'] . ':' . $proxy['auth']['pass'],
    ]);
  }

  private function applyToCurlIpTest(CurlHandle $ch)
  {
    curl_setopt($ch, CURLOPT_URL, 'https://ifconfig.me');
    curl_setopt($ch, CURLOPT_URL, 'ifconfig.me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, []);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
  }
}
