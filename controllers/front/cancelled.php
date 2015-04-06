<?php

class TraxxCancelledModuleFrontController extends ModuleFrontController 
{
	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		$this->display_column_left = false;
		parent::initContent();

		// Create a check value based on the secret key and the data received. // To make the following more readable, the field list has been split over two lines 
		$hash_check = $this->_SignData($_POST, Configuration::get("TRAXX-SECRETKEY"),'auth_status,auth_code,auth_message,auth_tranref,auth_cvv,auth_avs,card_code,card_desc,cart_id,cart_desc,cart_currency,cart_amount,tran_currency,tran_amount,tran_cust_ip');

		// Check that the signature in the message matches the expected value
		if ( strcasecmp($_POST['auth_hash'],$hash_check)!=0 ) {
			// Hash check does not match. Data may have been tampered with. 
			die('Check mismatch');
		}else{
			
			//$this->_logToFile(_LOG_DIR_.'debug.log', print_r($_POST, true));			
			//$this->module->validateOrder($_POST['cart_id'], Configuration::get('PS_OS_TRAXX_CANCELLED'), $_POST['tran_amount'], $this->module->displayName, NULL, false, (int)$this->context->currency->id, false, $_POST['xtra_secure_key']);		
		}		
	}

	//UNCOMMENT WHEN NEEDED
/*	private function _logToFile($filename, $msg) {
		$fd = fopen($filename, "a");
		$str = "[" . date("Y/m/d h:i:s", mktime()) . "] " . $msg;
		fwrite($fd, $msg . "\n");
		fclose($fd);
	}*/

	private function _SignData($post_data,$secretKey,$fieldList) {
	
		$signatureParams = explode(',', $fieldList); 
		$signatureString = $secretKey; 

		foreach ($signatureParams as $param) :
			
			if (array_key_exists($param, $post_data)) :
				$signatureString .= ':' . trim($post_data[$param]);
			else : 
				$signatureString .= ':';
			endif;
		endforeach;

		return sha1($signatureString);		
	}		
}