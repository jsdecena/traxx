<?php
/*
* 2007-2013 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class Traxx extends PaymentModule
{

	public function __construct()
	{
	$this->name = 'traxx';
	$this->tab = 'payments_gateways';
	$this->version = '0.1';
	$this->author = 'Jeff Simons Decena';
	$this->need_instance = 0;
	$this->ps_versions_compliancy = array('min' => '1.4', 'max' => '1.6');
	$this->module_key = "2ca0fd523254a92500efd487ac4db82a";

	parent::__construct();

	$this->displayName = $this->l('Credit Card Payment');
	$this->description = $this->l('Payment processing by Traxx');

	$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

	if (!Configuration::get('TRAXX'))      
	  $this->warning = $this->l('No name provided');
	}

	public function install()
	{
	  return parent::install() &&
	  	$this->registerHook('payment') &&
	  	Configuration::updateValue('PS_OS_TRAXX_AUTHORIZED', $this->_create_order_state('Credit Card Payment Authorized', 'traxxAuth', 'DarkOrange') ) &&
		Configuration::updateValue('PS_OS_TRAXX_DECLINED', $this->_create_order_state('Credit Card Payment Declined', 'traxxDecl', 'LightBlue') ) &&
		Configuration::updateValue('PS_OS_TRAXX_CANCELLED', $this->_create_order_state('Credit Card Payment Cancelled', 'traxxCancl', 'DarkBlue') );
	}

	public function uninstall()
	{
	  return parent::uninstall() && 
	  	Configuration::deleteByName('TRAXX-STOREID') &&
	  	Configuration::deleteByName('TRAXX-SECRETKEY') &&
	  	Configuration::deleteByName('TRAXX-TESTMODE') &&
	  	Db::getInstance()->delete('order_state', 'id_order_state = '. Configuration::get('PS_OS_TRAXX_AUTHORIZED')) &&
	  	Db::getInstance()->delete('order_state', 'id_order_state = '. Configuration::get('PS_OS_TRAXX_DECLINED')) &&
	  	Db::getInstance()->delete('order_state', 'id_order_state = '. Configuration::get('PS_OS_TRAXX_CANCELLED'));
	}

	public function hookPayment($params)
	{	//var_dump($this->context->customer->secure_key); die();
		if (!$this->active)
			return;
		if (!$this->checkCurrency($params['cart']))
			return;

		//GET TODAYS DATE
		$date 		= new DateTime();

		//GET THE BILLING ADDRESS OF THE CURRENT USER
		$sql = "
				SELECT pa.address1, pa.address2, pa.postcode, pa.city, pa.phone, pa.phone_mobile, pc.iso_code 
				FROM "._DB_PREFIX_."address AS pa
				JOIN "._DB_PREFIX_."country AS pc ON pc.id_country = pa.id_country
				WHERE pa.id_customer = 1
				AND pa.billing_address = 1
				AND pa.active = 1";

		$results = Db::getInstance()->executeS($sql);

		foreach ($results as $result ) :
			$bill_addr1  	= $result['address1'];
			$bill_addr2  	= $result['address2'];
			$bill_city 	 	= $result['city'];
			$bill_country  	= $result['iso_code'];
			$bill_zip  		= $result['postcode'];
			$bill_phone1  	= $result['phone'];
			$bill_phone2  	= $result['phone_mobile'];
		endforeach;

 		/* Build initial array using raw data, not encoded data */
		$post_data = array(
			'ivp_store' 		=> (int)Configuration::get("TRAXX-STOREID"),
			'ivp_amount' 		=> $this->context->cart->getOrderTotal(true, Cart::BOTH),
			'ivp_currency' 		=> $this->context->currency->iso_code,
			'ivp_test' 			=> (int)Configuration::get('TRAXX-TESTMODE'),
			'ivp_timestamp' 	=> (int)$date->format("U"),
			'ivp_cart' 			=> (int)$this->context->cart->id,			
			'ivp_desc' 			=> "Purchases",
			'ivp_extra' 		=> "bill,return,xtra",
			'bill_title'		=> "", 
			'bill_fname' 		=> $this->context->cookie->customer_firstname, 
			'bill_sname' 		=> $this->context->cookie->customer_lastname, 
			'bill_addr1' 		=> $bill_addr1, 
			'bill_addr2'		=> "", 
			'bill_addr3' 		=> "", 
			'bill_city' 		=> $bill_city, 
			'bill_region' 		=> "",
			'bill_country' 		=> strtolower($bill_country),			 
			'bill_zip' 			=> $bill_zip,
			'bill_email' 		=> $this->context->cookie->email,
			'bill_phone1'		=> $bill_phone1,
			'return_cb_auth'	=> $this->context->link->getModuleLink("traxx", "authentication"),
			'return_cb_decl'	=> $this->context->link->getModuleLink("traxx", "declined"),
			'return_cb_can'		=> $this->context->link->getModuleLink("traxx", "cancelled"),
			'return_auth'		=> "auto:".$this->context->link->getPageLink("history"),
			'return_decl'		=> "auto:".$this->context->link->getPageLink("history"),
			'return_can'		=> "auto:".$this->context->link->getPageLink("order"),
			'xtra_fields'		=> "xtra_secure_key",
			'xtra_secure_key'	=> $this->context->customer->secure_key
		);

		/* Calculate signatures after building main post_data array */
        $post_data['ivp_signature'] 	= $this->_SignData($post_data, Configuration::get("TRAXX-SECRETKEY"), 'ivp_store,ivp_amount,ivp_currency,ivp_test,ivp_timestamp,ivp_cart,ivp_desc,ivp_extra');
		$post_data['bill_signature'] 	= $this->_SignData($post_data, Configuration::get("TRAXX-SECRETKEY"), 'bill_title,bill_fname,bill_sname,bill_addr1,bill_addr2,bill_addr3,bill_city,bill_region,bill_country,bill_zip,bill_email,bill_phone1,ivp_signature');
		$post_data['return_signature'] 	= $this->_SignData($post_data, Configuration::get("TRAXX-SECRETKEY"), 'return_cb_auth,return_cb_decl,return_cb_can,return_auth,return_decl,return_can,ivp_signature');
		$post_data['xtra_signature'] 	= $this->_SignData($post_data, Configuration::get("TRAXX-SECRETKEY"), 'xtra_secure_key,xtra_fields,ivp_signature');

		$this->smarty->assign(array(
			'this_path' 		=> $this->_path,
			'this_path_bw' 		=> $this->_path,
			'this_path_ssl' 	=> Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',

			/*Traxx related values, html encode the values. Use values already calulated for post_data */
			'post_data' 		=> $post_data,
		));

		return $this->display(__FILE__, 'payment.tpl');
	}

	public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

	public function getContent()
	{
		$output = null;

		if ( Tools::isSubmit('submit'.$this->name) ) :

				$storeid 	= (int)Tools::getValue("storeid");
				$secretkey 	= Tools::getValue("secretkey");
				$testmode 	= Tools::getValue("testmode");

				if ( !Validate::isInt($storeid) || !$storeid || empty($storeid)) :

					$output .= $this->displayError( $this->l('Invalid Configuration value') );
				else:

					Configuration::updateValue('TRAXX-STOREID', $storeid);
					Configuration::updateValue('TRAXX-SECRETKEY', $secretkey);
					Configuration::updateValue('TRAXX-TESTMODE', $testmode);
					$output .= $this->displayConfirmation($this->l('Settings updated'));
				endif;
		endif;

		return $output.$this->displayForm();
	}	

	public function displayForm()
	{
		// Get default Language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$fields_form[0]['form'] = array(
			'legend' => array('title' => $this->l("Traxx Settings")),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Merchant Store ID'),
					'name' => 'storeid',
					'required' => true,
					'value' => Configuration::get('TRAXX-STOREID')
				),
				array(
					'type' => 'text',
					'label' => $this->l('Merchant Secret Key'),
					'name' => 'secretkey',
					'required' => true,
					'value' => Configuration::get('TRAXX-SECRETKEY')
				),
				array(
					'type' => 'switch',
					'label' => $this->l('Test mode?'),
					'name' => 'testmode',
					'is_bool' => true,
					'values' => array(
						array(
							'id' => 'active_on',
							'value' => 1,
							'label' => $this->l('Yes')
						),
						array(
							'id' => 'active_off',
							'value' => 0,
							'label' => $this->l('No')
						)						
					),
				)								
			),
			'submit' => array(
				'title' => $this->l("Save settings"),
				'class' => 'button',
				'name' => 'saveTraxx'
			)
		);

		$helper = new HelperForm();

		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		//Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		//Title and Toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar	= true;
		$helper->toolbar_scroll = true;
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
				'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
			'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
			'desc' => $this->l('Back to list')
			)
		);

		// Load current value
		$helper->fields_value['storeid'] = Configuration::get('TRAXX-STOREID');
		$helper->fields_value['secretkey'] = Configuration::get('TRAXX-SECRETKEY');
		$helper->fields_value['testmode'] = Configuration::get('TRAXX-TESTMODE');
		return $helper->generateForm($fields_form);
	}

    private function _create_order_state($label, $template = null, $color = 'DarkOrange')
    {
        //Create the new status
        $os = new OrderState();
        $os->name = array(
            '1' => $label,
            '2' => '',
            '3' => ''
        );

        $os->invoice = false;
        $os->unremovable = true;
        $os->color = $color;
        $os->template = $template;
        $os->send_email = true;

        $os->save();
        
        return $os->id;
    }

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