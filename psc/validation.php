<?php
include_once(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/../../init.php');

include_once(dirname(__FILE__).'/psc.php');

class PaysitecashValidation extends Psc
{

	public function __construct()
	{
		parent::__construct();
	}

	public function confirmOrder($custom)
	{
		function verification_ip($ip, $list){
			$list = explode('|',$list);
			return (in_array($ip, $list));
		}

		$errors = '';
		$result = false;
		
		$ref = '';
		$response ='';

		// Getting PSC data...
		if (function_exists('curl_exec')) {
			// curl ready
			$ch = curl_init();

			// If the above fails, then try the url with a trailing slash (fixes problems on some servers)
			if (!$ch)
				$errors .= $this->getL('connect').' '.$this->getL('curlmethodfailed');
			else
			{
				curl_setopt($ch, CURLOPT_URL, "http://www.paysite-cash.com/ip_list.txt");
				//curl_setopt($ch, CURLOPT_REQUEST, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
				curl_setopt($ch, CURLOPT_USERAGENT, "Billing Confirmation");
				curl_setopt($ch, CURLOPT_TIMEOUT,60);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

				$response = curl_exec($ch);
				$curl_error = curl_error($ch);
				$curl_info = curl_getinfo($ch);
				curl_close($ch);
			}
		} else {
			$response = file_get_contents("http://www.paysite-cash.com/ip_list.txt");
		}

		if (!empty($response)) {
			
			if (!verification_ip($_SERVER['REMOTE_ADDR'], $response)){/*$errors = $this->getL('access');*/}

			$etat 		= $custom['etat'];
			$divers 	= $custom['divers'];

			$url = base64_decode($divers);
			$key	= '';
						
			if(!preg_match('/key\=([a-fA-F0-9]+)/',$url, $matches)){$errors = "#1"/*$this->getL('access')*/;}
			$key	= $matches[1];

			if(!preg_match('/ref\=([0-9]+)/',$url, $matches)){$errors = "#2"/*$this->getL('access')*/;}
			$ref	= $matches[1];

			if(!preg_match('/sk\=([^\&]+)/',$url, $matches)){$errors = "#3"/*$this->getL('access')*/;}
			$sk	= $matches[1];

			if(!preg_match('/id_cart\=([0-9]+)/',$url, $matches)){$errors = "#4"/*$this->getL('access')*/;}
			$id_cart	= $matches[1];

			if(!preg_match('/id_module\=([^\&]+)/',$url, $matches)){$errors = "#5"/*$this->getL('access')*/;}
			$id_module	= $matches[1];

			if($key != md5("secret_key".(0)."".$ref)){
				$errors = "$key != ".md5('secret_key'.(0).''.$ref)/*$this->getL('access')*/;
			}

			if (empty($errors)){
			//	fwrite($fp, "update cart\n");

				switch ($etat) {
					case 'ko':
						$cart = new Cart(intval($ref));
						
						$cart_details = $cart->getSummaryDetails(null, true);
						$cart_hash = sha1(serialize($cart->nbProducts()));
						
						$this->context->cart = $cart;
						$address = new Address((int)$cart->id_address_invoice);
						$this->context->country = new Country((int)$address->id_country);
						$this->context->customer = new Customer((int)$cart->id_customer);
						$this->context->language = new Language((int)$cart->id_lang);
						$this->context->currency = new Currency((int)$cart->id_currency);
						
						if (isset($cart->id_shop))
							$this->context->shop = new Shop($cart->id_shop);
						
						if (_PS_VERSION_ < '1.5')
							$shop = null;
						else
						{
							$shop_id = $this->context->shop->id;
							$shop = new Shop($shop_id);
						}
						$message = 'Payment declined. Error details: '.$custom['errordetail'];
						$customer = new Customer((int)$cart->id_customer);
						$transaction = array(
							'currency' => $custom['devise_sent'],
							'id_invoice' => $ref,
							'id_transaction' => $custom['id_trans'],
							'transaction_id' => $custom['id_trans'],
							'total_paid' => $custom['montant_sent'],
							'payment_date' => $custom['time'],
							'payment_status' => $custom['etat']
						);
						
						//$this->validateOrder(intval($ref), _PS_OS_ERROR_, 0, $this->displayName, 'Payment declined. Error details: . ');					
						$this->validateOrder($cart->id, (int)Configuration::get('PS_OS_ERROR'), 0, $this->displayName, $message, $transaction, $cart->id_currency, false, $customer->secure_key, $shop);
						
						$errors = $this->getL('verified');
						break;
					case 'end':
						$errors = $this->getL('verified');
						break;
					case 'chargeback':
					case 'refund':
						$errors = $this->getL('verified');
						break;
					case 'wait':
						break;
					case 'ok':
						$cart = new Cart(intval($ref));
						
						$cart_details = $cart->getSummaryDetails(null, true);
						$cart_hash = sha1(serialize($cart->nbProducts()));
						
						$this->context->cart = $cart;
						$address = new Address((int)$cart->id_address_invoice);
						$this->context->country = new Country((int)$address->id_country);
						$this->context->customer = new Customer((int)$cart->id_customer);
						$this->context->language = new Language((int)$cart->id_lang);
						$this->context->currency = new Currency((int)$cart->id_currency);
						
						if (isset($cart->id_shop))
							$this->context->shop = new Shop($cart->id_shop);
						
						
						if (!$cart->id) {
							$errors = $this->getL('cart').'<br />';
						}
						elseif (Order::getOrderByCartId(intval($ref))){
							
							if (function_exists('curl_exec')){
								$ch = curl_init();
								
								curl_setopt($ch, CURLOPT_URL, 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'order-confirmation.php?key='.$sk.'&id_cart='.$id_cart.'&id_module='.$id_module);
								curl_setopt($ch, CURLOPT_REQUEST, 1);
								curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
								curl_setopt($ch, CURLOPT_USERAGENT, "Billing Confirmation");
								curl_setopt($ch, CURLOPT_TIMEOUT,60);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
								curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
						
								$response	= curl_exec($ch);
								$curl_error	= curl_error($ch);
								$curl_info	= curl_getinfo($ch);
								curl_close($ch);
							}else{
								$response	= file_get_contents('http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'order-confirmation.php?key='.$sk.'&id_cart='.$id_cart.'&id_module='.$id_module);
							}		
							$errors = $this->getL('order').'<br />';
						}
						if (_PS_VERSION_ < '1.5')
							$shop = null;
						else
						{
							$shop_id = $this->context->shop->id;
							$shop = new Shop($shop_id);
						}
						$customer = new Customer((int)$cart->id_customer);
						$transaction = array(
							'currency' => $custom['devise_sent'],
							'id_invoice' => $ref,
							'id_transaction' => $custom['id_trans'],
							'transaction_id' => $custom['id_trans'],
							'total_paid' => $custom['montant_sent'],
							'payment_date' => $custom['time'],
							'payment_status' => $custom['etat']
						);
						$message = "Payment accepted. ".$custom['errordetail'];
						$this->validateOrder($cart->id, (int)Configuration::get('PS_OS_PAYMENT'), $custom['montant_sent'], $this->displayName, $message, $transaction, $cart->id_currency, false, $customer->secure_key, $shop);
						break;
				}
			}
		}else
		$errors = $this->getL('connect').$this->getL('nomethod');
	}
}

$validation = new PaysitecashValidation();
$validation->confirmOrder($_POST);

?>