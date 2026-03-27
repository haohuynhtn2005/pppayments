<?php

if(!function_exists('get_random_wc_api_endpoint')) {
    function get_random_wc_api_endpoint()
    {
        $endpoints = [
            "/wc-api/contact",
            "/wc-api/support-center",
            "/wc-api/need-help",
            "/wc-api/send-us-a-message",
            "/wc-api/drop-us-a-message",
            "/wc-api/speak-with-us",
            "/wc-api/talk-to-us",
            "/wc-api/connect-with-us",
            "/wc-api/reach-out",
            "/wc-api/get-in-touch"
        ];
        
        return $endpoints[array_rand($endpoints)];
    }
}


if (!function_exists('log_error')) {

  /**
   * 
   */
  function log_error($file_name, $message)
  {
    $log_dir = __DIR__ . "/../logs";

    if (!file_exists($log_dir)) {
      mkdir($log_dir, 0777, true);
    }

    $log_file = $log_dir . "{$file_name}_" . date("Y-m-d") . ".txt";

    $log_data = date('Y-m-d H:i:s') . " - " . $message . "\n";

    file_put_contents($log_file, $log_data, FILE_APPEND);
  }
}

if(!function_exists('convertSiteUrlToSiteName')) {
    
    function convertSiteUrlToSiteName($homeUrl)
    {
        $parsedUrl = parse_url($homeUrl);

        $host = $parsedUrl['host'] ?? '';
        
        $name = '';
        if(preg_match('/(.*?)\.(.*?)/', $host, $m)) {
          $name = ucfirst($m[1]);
        }
        
        return $name;
    }
}

if (!function_exists('plugin_custom_log')) {
  function plugin_custom_log($message = '', $fileName = '')
  {

    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    // Lấy đường dẫn thư mục của file hiện tại (trong plugin)
    $plugin_dir = plugin_dir_path(__FILE__);

    // Tạo thư mục logs nếu chưa có
    $log_dir = $plugin_dir . '../logs/';
    
   // var_dump($log_dir);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // File log theo ngày
    $log_file = $log_dir . 'log-' . date('Y-m-d') . '.log';

    // Ghi log
    $formatted_message = '[' . date('H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
  }
}

if (!function_exists('telegram_push_log')) {

  if (!defined('TELEGRAM_PUSH_LOG_ENABLED')) {
    define('TELEGRAM_PUSH_LOG_ENABLED', true);
  }

  if (!defined('TELEGRAM_BOT_TOKEN')) {
    define('TELEGRAM_BOT_TOKEN', '6124763967:AAHfARyeqRvonizo-9l-RgRULBeioI10GO0');
  }

  if (!defined('TELEGRAM_CHAT_ID')) {
    define('TELEGRAM_CHAT_ID', '-1001626566210');
  }

  /**
   * telegram push log
   */
  function telegram_push_log($message, $idTele = null, $threadId = '88548'): void
  {
    if (!TELEGRAM_PUSH_LOG_ENABLED) {
      return;
    }

    if (empty(TELEGRAM_BOT_TOKEN) && empty(TELEGRAM_CHAT_ID)) {
      return;
    }

    if (is_array($message)) {
      $message = print_r($message, true);
    }

    $host = 'localhost';
	if($_SERVER['HTTP_HOST']) {
		$host = strtoupper($_SERVER['HTTP_HOST']);
	}

	$message = $host ."|" . $message;

    $bot_token = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot$bot_token/sendMessage";
    $data = [
      'chat_id' => $idTele !== null ? $idTele : TELEGRAM_CHAT_ID,
      'text' => $message
    ];
    
    if ($threadId !== null) {
        $data['message_thread_id'] = (int)$threadId;
    }

    $options = [
      'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type:application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
      ],
    ];
    $context = stream_context_create($options);

    // Xử lý kết quả trả về từ API Telegram
    $result = @file_get_contents($url, false, $context);
    if ($result === FALSE) {
        plugin_custom_log("Telegram push log failed: Unable to send message.");
      //error_log("Telegram push log failed: Unable to send message.");
    }
  }
}

if (!function_exists('dd')) {
  function dd($value)
  {
    echo "<pre>";
    var_dump($value);
    die();
    echo "</pre>";
  }
}

if (!function_exists('postCURL')) {
  define('CURL_LOG_DATA', true);
  /**
   * Post Url
   */
  function postCURL(string $url, $PARAMS, $METHOD = "POST", $authorization = '')
  {
    $msg_data = "Request URL: " . $url . "\n";
    $msg_data .= "Request Params: " . json_encode($PARAMS) . "\n";

    try {

      $curl = curl_init();
      curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS =>  http_build_query($PARAMS),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $METHOD
      ));

      if (!empty($authorization)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: ' . $authorization));
      }

      $response = curl_exec($curl);

      if (curl_errno($curl)) {
        throw new Exception(curl_error($curl));
      }

      if (CURL_LOG_DATA) {
        // Log the response details
        $msg_data .= "Response: " . $response . "\n";
        log_error('postCURL', $msg_data);
      }

      curl_close($curl);
      return $response;
    } catch (\Exception $e) {
      log_error('postCURL', "Error : {$e} ",);
      throw $e;
    }
  }
}

if(!function_exists('getUserIP')) {
  /**
   * Get urser IP
   */
  function getUserIP()
	{
		// Get real visitor IP behind CloudFlare network
		if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
				  $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
				  $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
		}
		$client  = @$_SERVER['HTTP_CLIENT_IP'];
		$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
		$remote  = $_SERVER['REMOTE_ADDR'];

		if(filter_var($client, FILTER_VALIDATE_IP))
		{
			$ip = $client;
		} elseif(filter_var($forward, FILTER_VALIDATE_IP)) {
			$ip = $forward;
		} else {
			$ip = $remote;
		}
		return $ip;
	}
}

if(!function_exists('genSignature')) {
    function genSignature($key, $paymentCode)
    {
        $str = getUserIP() . $key . $paymentCode;
        
        $genSignLog = [
            'id' => getUserIP(),
            'key' => $key,
            'paymentCode' => $paymentCode
        ];
        
        telegram_push_log('signgen: ' . print_r($genSignLog, true));
        
        return md5($str);
    }
}

if(!function_exists('pmCodeEncryptLinkToCode')) {
    function pmCodeEncryptLinkToCode(string $link)
    {
        $encode = base64url_encode($link);

        $encode = strrev($encode);
        
        return $encode;
    }
}

if(!function_exists('pmCodeDecryptToLink')) {
    function pmCodeDecryptToLink(string $code)
    {
        $decrypted = base64url_decode(strrev($code));
        
        return $decrypted;
    }
}

if(!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if(!function_exists('base64url_decode')) {
    function base64url_decode($data) {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if(!function_exists('ip_in_range')) {
    
    function ip_in_range( $ip, $range )
    {
    	if ( strpos( $range, '/' ) == false ) {
    		$range .= '/32';
    	}
    	// $range is in IP/CIDR format eg 127.0.0.1/24
    	list( $range, $netmask ) = explode( '/', $range, 2 );
    	$range_decimal = ip2long( $range );
    	$ip_decimal = ip2long( $ip );
    	$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
    	$netmask_decimal = ~ $wildcard_decimal;
    	return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
    }
}

if(!function_exists('redirect_url')) {
    function redirect_url($url, $second = 0)
    {
        if ($second != 0) {
            $second = $second * 1000;
            echo "<script>
                setTimeout(function() {
                    window.location.href = '$url';
                }, {$second}); // 5000ms
            </script>";
        } else {
            echo "<script>window.location.href='" . $url . "';</script>";
        }
        exit;
    }
}

