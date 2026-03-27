<?php
class Shield_API
{
	protected $merchant_key = '';
	protected $shield_key = '';
	protected $API_URL = '';

	public function __construct($API_URL, $merchant_key, $shield_key)
	{
		$this->merchant_key    = $merchant_key;
		$this->shield_key = $shield_key;
		$this->API_URL = $API_URL;
	}

	/**
	 * Api
	 */
	public function webhookIPN($fields = array(), $timeout = 60)
	{
		$url = $this->API_URL . '/transaction/webhooks';
	
    	$postvars = http_build_query($fields);
		
    	$log_file = 'webhookIPN';

		$msg = "API URL: $url \n";

		//telegram_push_log($msg);
        
        try {
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

			$msg .= "merchant_key: {$this->merchant_key} - shield_key: {$this->shield_key}\n";
        
            $response = curl_exec($ch);
        
            if ($response === false) {
                $msg .= curl_error($ch);
                telegram_push_log($msg);
                log_error($log_file, "Exception: " . $msg);
            }

			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode != 200) {
                log_error($log_file, $msg);
                telegram_push_log($msg);
            }
        
            if (json_last_error() !== JSON_ERROR_NONE) {
                $json_error_msg = 'shield_api.webhookIPN: JSON Decode Error: ' . json_last_error_msg();
                log_error($log_file, $json_error_msg);
                telegram_push_log($json_error_msg);
                return array(
                    'status' => false,
                    'message' => $json_error_msg
                );
            }

			$data = json_decode($response, true);
			
			telegram_push_log("{$msg}\n".print_r($data, true));
            curl_close($ch);

			return $data;
        } catch (\Exception $e) {
            $message = "Error:\n";
            $message .= "Message: " . $e->getMessage() . "\n";
            $message .= "File: " . $e->getFile() . "\n";
            $message .= "Line: " . $e->getLine() . "\n";
        
            log_error($log_file, $message);
            telegram_push_log($message);
        
            throw $e;
        }
	}

	/**
	 * Create order
	 * Dont not use in this site
	 */
	public function createOrder($fields = array(), $timeout = 60)
	{
		$url = $this->API_URL . '/transaction/create';
		$log_file = "createOrder";
		$postvars = http_build_query($fields);  //TODO: @author: brooklyn change $postvars
		// foreach ($fields as $key => $value) {
		// 	$postvars .= $key . "=" . $value . "&";
		// }
		try {
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
				$error_msg = curl_error($ch);
				log_error($log_file, "Exception: " . $error_msg);
			}

			curl_close($ch);

			$data = json_decode($response, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				
				$json_error_msg = 'shield_api.createOrder: JSON Decode Error: ' . json_last_error_msg();
				log_error($log_file, $json_error_msg);
				telegram_push_log($json_error_msg);
				return array(
					'status' => false,
					'message' => $json_error_msg
				);
			}

			return $data;
		} catch (Exception $e) {
			$message = "Error:\n";
			$message.= "Message: ". $e->getMessage() . "\n";
			$message.= "File: " . $e->getFile() . "\n";
			$message.= "Line: " . $e->getLine() . "\n";


			log_error($log_file, $message);
			telegram_push_log($message);

			return array(
				'status' => false,
				'message' => $e->getMessage()
			);
		}
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

		$log_file = "getShieldInfo";
		$postvars = http_build_query($fields);  //TODO: @author: brooklyn change $postvars
		// foreach ($fields as $key => $value) {
		// 	$postvars .= $key . "=" . $value . "&";
		// }
		try {
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
				$error_msg = curl_error($ch);
				log_error($log_file, "Exception: " . $error_msg);
				telegram_push_log($error_msg);
			}

			curl_close($ch);

			$data = json_decode($response, true);

			if (json_last_error() !== JSON_ERROR_NONE) {

				$json_error_msg = 'shield_api.getShieldInfo: JSON Decode Error: ' . json_last_error_msg();
				log_error($log_file, $json_error_msg);
				telegram_push_log($json_error_msg);
				return array(
					'status' => false,
					'message' => $json_error_msg
				);
			}

			return $data;
		} catch (Exception $e) {
			$message = "Error:\n";
			$message.= "Message: ". $e->getMessage() . "\n";
			$message.= "File: " . $e->getFile() . "\n";
			$message.= "Line: " . $e->getLine() . "\n";


			log_error($log_file, $message);
			telegram_push_log($message);

			return array(
				'status' => false,
				'message' => $e->getMessage()
			);
		}
	}
}