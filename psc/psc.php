<?php

class Psc extends PaymentModule
{
	private	$_html = '';
	private $_postErrors = array();
	private $_postMessages = array();

	public function __construct()
	{
		$this->name = 'psc';
		$this->tab = 'payments_gateways';
		$this->version = '1.9.0';

		$this->currencies = true;
		$this->currencies_mode = 'radio';

		parent::__construct();

		/* The parent construct is required for translations */
		$this->page = basename(__FILE__, '.php');
		$this->displayName = $this->l('Paysite-cash');
		$this->description = $this->l('Accepts payments by Paysite-cash');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');
	}

	public function getPSCUrl()
	{
		return Configuration::get('PSC_GATEWAY')=='easypay' ? 'https://secure.easy-pay.net/' :'https://billing.paysite-cash.biz/';
	}

	public function install()
	{
		if (!parent::install() OR !$this->registerHook('payment'))
			return false;
		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall())
			return false;
		return true;
	}

	public function getContent()
	{
		$this->_html = '<h2>Paysite-cash</h2>';
		if (isset($_POST['submitPSC']) && !isset($_POST['pscaccount2']))
		{
			if (empty($_POST['siteid']))
				$this->_postErrors[] = $this->l('Paysite-cash siteid have to be a value');
			if (!isset($_POST['testmode']))
				$_POST['testmode'] = 1;
			if (!isset($_POST['debugmode']))
				$_POST['debugmode'] = 1;
			if (!isset($_POST['gateway']))
				$_POST['gateway'] = 'paysite';
			if (!sizeof($this->_postErrors))
			{
				Configuration::updateValue('PSC_SITEID', $_POST['siteid']);
				Configuration::updateValue('PSC_TESTMODE', intval($_POST['testmode']));
				Configuration::updateValue('PSC_DEBUGMODE', intval($_POST['debugmode']));
				Configuration::updateValue('PSC_GATEWAY', $_POST['gateway']);
				$this->displayConf();
			} else {
				$this->displayErrors();
			}
		} elseif (($_POST['pscaccount2']=='1') && isset($_POST['submitnewsitePSC'])) {
			
			$service = $_POST['service'];

			$default = array (
			  'is_selling_goods' => false,
			  'referrer_url' => '',
			  'after_payment_url' => '',
			  'cancelled_payment_url' => '',
			  'backoffice_url' => '',
			  'backoffice_url_username' => '',
			  'backoffice_url_password' => '',
			  'accept_payment_from_abroad' => true,
			  'accept_free_emails' => true,
			  'accept_multiple_subscriptions' => true,
			  'multiple_payments_alert_limit' => 1,
			  'validate_by_sms' => false,
			  'validate_by_phone' => false,
			  'revalidate_after_months' => 1,
			  'revalidate_after_amount' => 0,
			  'request_activation' => false,
			);

			$site = array_merge($default, $_POST['site_create']);

			$checkboxes = array(
				'is_selling_goods', 'accept_payment_from_abroad', 'accept_free_emails', 'accept_multiple_subscriptions',
				'validate_by_sms', 'validate_by_phone', 'request_activation'
			);
			foreach ($checkboxes as $name) {
				if (array_key_exists($name, $_POST['site_create'])) {
					$site[$name] = true;
				}
			}

			$client = new SoapClient('https://billing.paysite-cash.biz/service/site_creation_service.php?wsdl', array('location' => 'https://billing.paysite-cash.biz/service/site_creation_service.php', 'uri' => 'https://billing.paysite-cash.biz/service/site_creation_service.php','trace' => true));
			try {
				$response = $client->createSite($service['api_key'], $site);
				if (is_int($response)) {
					$siteid=$response;
					Configuration::updateValue('PSC_SITEID', $siteid);
					$this->_postMessages[] = $this->l('New Site successfully created in Paysite-cash system!');
					$this->_postMessages[] = $this->l('Site id: ').$siteid;
					$this->displayMessages();
				} else {
					$msg = "Response: ".$response;
					$this->_postErrors[] = $this->l('Site creation error!');
					$this->_postErrors[] = $this->l($msg);
					$this->displayErrors();
				}
			} catch (SoapFault $e) {
				$display_soap_fault = true;
				switch ($e->getMessage()) {
					case 'Failed to login':
						$soap_error_message = $this->l('Unable to login');
						break;

					case 'existing url':
						$soap_error_message = $this->l('Site with URL %s is already registered',htmlspecialchars($e->detail));
						break;

					case 'required field':
						$soap_error_message = $this->l('Missing required field <code> %s </code>', $e->detail);
						break;

					case 'extra field':
						$soap_error_message = $this->l('Extra field <code> %s </code> passed', htmlspecialchars($e->detail));
						break;

					default:
						$soap_error_message = $this->l('Unexpected error occured: ').$e->getMessage();
						break;
				}
				$msg = $this->l('Site creation error!').$e->getMessage()."\n ".$this->l('Error details:').$e->detail;
				$this->_postErrors[] = $this->l($msg);
				$this->displayErrors();
			}
		} elseif (($_POST['pscapikey']=='1') && isset($_POST['getnewAPIkey'])) {
			ini_set('soap.wsdl_cache_enabled', false);
			$client = new SoapClient('https://billing.paysite-cash.biz/service/site_creation_service.php?wsdl', array('location' => 'https://billing.paysite-cash.biz/service/site_creation_service.php', 'uri' => 'https://billing.paysite-cash.biz/service/site_creation_service.php', 'trace' => true));

			$service = $_POST['service'];
			try {
				$api_key = $client->getApiKey($service['username'], md5($service['password']));
				Configuration::updateValue('PSC_API_KEY', $api_key);
				
				$this->_postMessages[] =  $this->l('API Key successfully retrieved!');
				$this->_postMessages[] =  $this->l('API Key: ').$api_key;
				$this->displayMessages();
			} catch (SoapFault $e) {
				$this->_postErrors[] = $this->l('API key retrieval failure!');
				$this->_postErrors[] = $this->l($e->getMessage());
				$this->displayErrors();
			}
		} elseif (($_POST['pscaccount3']=='0') && isset($_POST['registerPSC'])) {
			define('SERVICE_LOCATION', 'https://billing.paysite-cash.biz/service/merchant_reg_srv.php');
			$view = 'index';
			$apikey = '7b841160f81a70afe52c91b74995ed3d';

			if ($view != 'error' && array_key_exists('register', $_POST)) {
				$view = 'response';

				$client = new SoapClient(null, array(
					'location' => SERVICE_LOCATION,
					'uri' => SERVICE_LOCATION,
					'trace' => true));

				$params = $_POST['register'];

				try {
					$response = (array) $client->lightRegister($apikey, $params);
					$status = $response['status'];
					$username = $response['username'];
					$merchant_id = $response['merchant_id'];
					$msg = $output;
					Configuration::updateValue('PSC_ACCOUNT', $status);
					$this->_postMessages[] =  $this->l('Merchant registration success!');
					$this->_postMessages[] =  $this->l('Username: ').$username;
					$this->_postMessages[] =  $this->l('Status: ').$status;
					$this->_postMessages[] =  $this->l('Merchant ID: ').$merchant_id;
					$this->displayMessages();
				} catch (SoapFault $e) {
					$output = $e->getMessage();
					$dump = print_r($e, true);
					
					$msg = $e->getMessage();
					
					if ($output = "wrong api key") {$msg = $this->l('wrong api key');}
					else if ($output = "required field") {$msg = $this->l('required field');}
					else if ($output = "extra field") {$msg = $this->l('extra field');}
					else if ($output = "wrong country") {$msg = $this->l('wrong country');}
					else if ($output = "username too short") {$msg = $this->l('username too short');}
					else if ($output = "username is taken") {$msg = $this->l('username is taken');}
					else if ($output = "email not valid") {$msg = $this->l('email not valid');}
					else if ($output = "email in use") {$msg = $this->l('email in use');}
					
					$field = $e->detail;
					$this->_postErrors[] = ($this->l('Merchant registration failure! ')).($this->l('Error: ')).($this->l($msg)).': '.$field;
					
					$this->displayErrors();
				}
			}
		}
		
		$this->displayPSC();
		$this->displayFormSettings($msg);
		return $this->_html;
	}

	public function displayConf()
	{
		$this->_html .= '
		<div class="conf confirm">
			<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
			'.$this->l('Settings updated').'
		</div>';
	}

	public function displayErrors()
	{
		$nbErrors = sizeof($this->_postErrors);
		$this->_html .= '
		<div class="alert error">
			<h3>'.($nbErrors > 1 ? $this->l('There are') : $this->l('There is')).' '.$nbErrors.' '.($nbErrors > 1 ? $this->l('errors') : $this->l('error')).'</h3>
			<ol>';
		foreach ($this->_postErrors AS $error)
			$this->_html .= '<li>'.$error.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}

	
	public function displayMessages()
	{
		$nbMessages = sizeof($this->_postMessages);
		$this->_html .= '
		<div class="alert success">
			<ol>';
		foreach ($this->_postMessages AS $message)
			$this->_html .= '<li>'.$message.'</li>';
		$this->_html .= '
			</ol>
		</div>';
	}

	public function displayPSC()
	{
		$this->_html .= '
		<img src="../modules/psc/psc.gif" style="float:left; margin-right:15px;" />
		<b>'.$this->l('This module allows you to accept payments by Paysite-cash.').'</b><br /><br />
		'.$this->l('If the client chooses this payment mode, your Paysite-cash account will be automatically credited.').'<br />
		'.$this->l('You need to configure your Paysite-cash account first before using this module.').'
		<br /><br /><br />';
	}

	public function displayFormSettings($msg)
	{
		
		$conf = Configuration::getMultiple(array('PSC_SITEID', 'PSC_TESTMODE', 'PSC_DEBUGMODE', 'PSC_GATEWAY', 'PSC_API_KEY', 'PSC_ACCOUNT'));
		$siteid = array_key_exists('siteid', $_POST) ? $_POST['siteid'] : (array_key_exists('PSC_SITEID', $conf) ? $conf['PSC_SITEID'] : '');
		
		$testmode = array_key_exists('testmode', $_POST) ? $_POST['testmode'] : (array_key_exists('PSC_TESTMODE', $conf) ? $conf['PSC_TESTMODE'] : '');
		$debugmode = array_key_exists('debugmode', $_POST) ? $_POST['debugmode'] : (array_key_exists('PSC_DEBUGMODE', $conf) ? $conf['PSC_DEBUGMODE'] : '');
		$gateway = array_key_exists('gateway', $_POST) ? $_POST['gateway'] : (array_key_exists('PSC_GATEWAY', $conf) ? $conf['PSC_GATEWAY'] : '');
    
		$api_key = array_key_exists('api_key', $_POST) ? $_POST['api_key'] : (array_key_exists('PSC_API_KEY', $conf) ? $conf['PSC_API_KEY'] : '');
		
		$pscaccount = array_key_exists('pscaccount', $_POST) ? $_POST['pscaccount'] : (array_key_exists('PSC_ACCOUNT', $conf) ? $conf['PSC_ACCOUNT'] : '');
		
		$field = strpos($msg, "required field");
		
		
		if (!isset($_POST['site_create'])) {
			$_POST['site_create']="";
		}
		if (!isset($_POST['site_create']['referrer_url'])) {
			$_POST['site_create']['referrer_url']="";
		}
		if (!isset($_POST['site_create']['after_payment_url'])) {
			$_POST['site_create']['after_payment_url']="";
		}
		if (!isset($_POST['site_create']['url'])) {
			$_POST['site_create']['url']="";
		}
				// Setting undefined variables
		
		if (!isset($_POST['register']['referrer_url'])) {
			$_POST['register']['referrer_url']="";
		}
		
		if (!isset($_POST['register']['status'])) {
			$_POST['register']['status']="";
		}
		if (!isset($_POST['register']['firstname'])) {
			$_POST['register']['firstname']="";
		}
		if (!isset($_POST['register']['lastname'])) {
			$_POST['register']['lastname']="";
		}
		if (!isset($_POST['register']['company'])) {
			$_POST['register']['company']="";
		}
		if (!isset($_POST['register']['registration_country'])) {
			$_POST['register']['registration_country']="";
		}
		if (!isset($_POST['register']['director_country'])) {
			$_POST['register']['director_country']="";
		}
		if (!isset($_POST['register']['eu_vat'])) {
			$_POST['register']['eu_vat']="";
		}
		if (!isset($_POST['register']['monthly_turnover'])) {
			$_POST['register']['monthly_turnover']="";
		}
		if (!isset($_POST['register']['postal_address'])) {
			$_POST['register']['postal_address']="";
		}
		if (!isset($_POST['register']['monthly_turnover'])) {
			$_POST['register']['monthly_turnover']="";
		}
		if (!isset($_POST['register']['zipcode'])) {
			$_POST['register']['zipcode']="";
		}
		if (!isset($_POST['register']['city'])) {
			$_POST['register']['city']="";
		}		
		if (!isset($_POST['register']['country'])) {
			$_POST['register']['country']="";
		}	
		if (!isset($_POST['register']['phone_number'])) {
			$_POST['register']['phone_number']="";
		}	
		if (!isset($_POST['register']['email'])) {
			$_POST['register']['email']="";
		}
		if (!isset($_POST['register']['main_website_url'])) {
			$_POST['register']['main_website_url']="";
		}
		if (!isset($_POST['register']['business_description'])) {
			$_POST['register']['business_description']="";
		}
		if (!isset($_POST['register']['username'])) {
			$_POST['register']['username']="";
		}
		if (!isset($_POST['register']['site_url'])) {
			$_POST['register']['site_url']="";
		}
		if (!isset($_POST['register']['site_confirmation_url'])) {
			$_POST['register']['site_confirmation_url']="";
		}
		if (!isset($_POST['register']['confirm_user'])) {
			$_POST['register']['confirm_user']="";
		}
		if (!isset($_POST['register']['confirm_password'])) {
			$_POST['register']['confirm_password']="";
		}
		if (!isset($_POST['register']['alert_url'])) {
			$_POST['register']['alert_url']="";
		}
		if (!isset($_POST['register']['after_payment_url'])) {
			$_POST['register']['after_payment_url']="";
		}
		if (!isset($_POST['register']['cancelled_payment_url'])) {
			$_POST['register']['cancelled_payment_url']="";
		}
		if (!isset($_POST['register']['cancelled_payment_url'])) {
			$_POST['register']['cancelled_payment_url']="";
		}
		
		if (!isset($_POST['site_create']['name'])) {
			$_POST['site_create']['name']="";
		}

		if (!isset($_POST['site_create']['customer_contact_email'])) {
			$_POST['site_create']['customer_contact_email']="";
		}
		if (!isset($_POST['site_create']['is_selling_goods'])) {
			$_POST['site_create']['is_selling_goods']="";
		}

		if (!isset($_POST['site_create']['cancelled_payment_url'])) {
			$_POST['site_create']['cancelled_payment_url']="";
		}
		if (!isset($_POST['site_create']['backoffice_url'])) {
			$_POST['site_create']['backoffice_url']="";
		}
		if (!isset($_POST['site_create']['backoffice_url_username'])) {
			$_POST['site_create']['backoffice_url_username']="";
		}
		if (!isset($_POST['site_create']['backoffice_url_password'])) {
			$_POST['site_create']['backoffice_url_password']="";
		}
		if (!isset($_POST['site_create']['multiple_payments_alert_limit'])) {
			$_POST['site_create']['multiple_payments_alert_limit']="";
		}
		if (!isset($_POST['site_create']['accept_payment_from_abroad'])) {
			$_POST['site_create']['accept_payment_from_abroad']="";
		}
		if (!isset($_POST['site_create']['accept_free_emails'])) {
			$_POST['site_create']['accept_free_emails']="";
		}
		if (!isset($_POST['site_create']['accept_multiple_subscriptions'])) {
			$_POST['site_create']['accept_multiple_subscriptions']="";
		}
		if (!isset($_POST['site_create']['validate_by_sms'])) {
			$_POST['site_create']['validate_by_sms']="";
		}
		if (!isset($_POST['site_create']['validate_by_phone'])) {
			$_POST['site_create']['validate_by_phone']="";
		}
		if (!isset($_POST['site_create']['revalidate_after_months'])) {
			$_POST['site_create']['revalidate_after_months']="";
		}
		if (!isset($_POST['site_create']['revalidate_after_amount'])) {
			$_POST['site_create']['revalidate_after_amount']="";
		}
		if (!isset($_POST['site_create']['request_activation'])) {
			$_POST['site_create']['request_activation']="";
		}
		
		if (!isset($_POST['site_create']['url']) || trim($_POST['site_create']['url'])=='') {
			$_POST['site_create']['url']="http://".$_SERVER['SERVER_NAME']."";
		}
		if (!isset($_POST['site_create']['referrer_url']) || trim($_POST['site_create']['referrer_url'])=='') {
			$_POST['site_create']['referrer_url']="http://".$_SERVER['SERVER_NAME']."";
		}	
		if (!isset($_POST['site_create']['after_payment_url']) || trim($_POST['site_create']['after_payment_url'])=='') {
			$_POST['site_create']['after_payment_url']="http://".$_SERVER['SERVER_NAME']."/order-confirmation.php";
		}
		if (!isset($_POST['site_create']['cancelled_payment_url']) || trim($_POST['site_create']['cancelled_payment_url'])=='') {
			$_POST['site_create']['cancelled_payment_url']="http://".$_SERVER['SERVER_NAME']."/order-confirmation.php";
		}
		if (!isset($_POST['site_create']['backoffice_url']) || trim($_POST['site_create']['backoffice_url'])=='') {
			$_POST['site_create']['backoffice_url']="http://".$_SERVER['SERVER_NAME']."/modules/psc/validation.php";
		}

		if (!isset($_POST['register']['main_website_url']) || trim($_POST['register']['main_website_url'])=='') {
			$_POST['register']['main_website_url']="http://".$_SERVER['SERVER_NAME']."/";
		}
		if (!isset($_POST['register']['referrer_url']) || trim($_POST['register']['referrer_url'])=='') {
			$_POST['register']['referrer_url']="http://".$_SERVER['SERVER_NAME']."/";
		}	
		if (!isset($_POST['register']['after_payment_url']) || trim($_POST['register']['after_payment_url'])=='') {
			$_POST['register']['after_payment_url']="http://".$_SERVER['SERVER_NAME']."/order-confirmation.php";
		}
		if (!isset($_POST['register']['cancelled_payment_url']) || trim($_POST['register']['cancelled_payment_url'])=='') {
			$_POST['register']['cancelled_payment_url']="http://".$_SERVER['SERVER_NAME']."/order-confirmation.php";
		}
		if (!isset($_POST['register']['site_confirmation_url']) || trim($_POST['register']['site_confirmation_url'])=='') {
			$_POST['register']['site_confirmation_url']="http://".$_SERVER['SERVER_NAME']."/modules/psc/validation.php";
		}
		
		$this->_html .= '
		<style>
		label {
			display: block;
			float: left;
			width: 24%;
			text-align:left;
		}
		</style>
		<div style="z:10000;position:absolute;right:4.15%;top:90px;">
		<a href="http://store.templatemonster.com?aff=fredsoft"><img border="0" src="http://www.templatehelp.com/banners/new/TM/bankiller.gif" width="170" height="150" alt="bankiller"/></a>
		</div>
		<br /><br /><br /><br />
		'.($pscaccount || $api_key  ? '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
		<fieldset style="width:95%;">
			<legend><img src="../img/admin/contact.gif" />'.$this->l('Settings').'</legend>
			<label>'.$this->l('Paysite-cash Site ID').'</label>
			<div class="margin-form"><input type="text" size="33" name="siteid" value="'.htmlentities($siteid, ENT_COMPAT, 'UTF-8').'" /></div>
			<label>'.$this->l('Test mode').'</label>
			<div class="margin-form">
				<input type="radio" name="testmode" value="1" '.($testmode ? 'checked="checked"' : '').' /> '.$this->l('Yes').'
				<input type="radio" name="testmode" value="0" '.(!$testmode ? 'checked="checked"' : '').' /> '.$this->l('No').'
			</div>
			<label>'.$this->l('Debug mode').'</label>
			<div class="margin-form">
				<input type="radio" name="debugmode" value="1" '.($debugmode ? 'checked="checked"' : '').' /> '.$this->l('Yes').'
				<input type="radio" name="debugmode" value="0" '.(!$debugmode ? 'checked="checked"' : '').' /> '.$this->l('No').'
			</div>
			<label>'.$this->l('Gateway').'</label>
			<div class="margin-form">
		                <select name="gateway">
				<option value="paysite" '.($gateway=='paysite' ? 'selected="true"' : '').'>Paysite-Cash</option>
				<option value="easypay" '.($gateway=='easypay' ? 'selected="true"' : '').'>Easy-Pay</option>
				</select>
			</div>
			<br /><center><input type="submit" name="submitPSC" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form><br /><br />' : '').'
		
		<fieldset style="width:95%;">
			<legend><img src="../img/admin/add.gif" />'.$this->l('Creation of new site').'</legend>
			
			<label>'.$this->l('Have already account in Paysite-cash?').'</label>
			<div class="margin-form">
				<input type="radio" onclick="pscnewaccount()" name="pscaccount" value="1" '.($pscaccount || $api_key ? 'checked="checked"' : '').' /> '.$this->l('Yes').'
				<input type="radio" onclick="pscnewaccount()" name="pscaccount" value="0" '.(!$pscaccount && !$api_key  ? '' : '').' /> '.$this->l('No').'
			</div><br />
			<script type="text/javascript">
			 
			function pscnewaccount() {
				if (document.getElementsByName("pscaccount")[0].checked==true) {
					document.getElementsByName("pscnewaccount")[0].style.display=""
					document.getElementsByName("pscaccount2")[0].value="1"
					document.getElementsByName("pscregister")[0].style.display="none"
				} else {
					document.getElementsByName("pscnewaccount")[0].style.display="none"
					document.getElementsByName("pscaccount2")[0].value="0"
					document.getElementsByName("pscregister")[0].style.display=""
				}
			}
			</script>
			<div name="pscregister" style="display:none">
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<input type="hidden" id="pscaccount3" name="pscaccount3" value="0" />
			<input type="hidden" id="language" name="register[language]" value="'.strtolower($this->context->language->iso_code).'" />
			
			<hr>
						
			<label>'.$this->l('Merchant').'</label>
			<div class="margin-form">&nbsp;</div>
			<label for="status">'.$this->l('Status').'</label>
			<div class="margin-form">
				<select id="status" name="register[status]" />
					<option '.($_POST['register']['status']=="individual" ? 'selected="true"' : '').' value="individual">Individual</option>
					<option '.($_POST['register']['status']=="company" ? 'selected="true"' : '').' value="company">Company</option>
				</select>
			</div>
			<label for="firstname">'.$this->l('Firstname').'</label>
			<div class="margin-form"><input type="text" size="33" id="firstname" name="register[firstname]" value="'.$_POST['register']['firstname'].'" /></div>
			<label for="lastname">'.$this->l('Lastname').'</label>
			<div class="margin-form"><input type="text" size="33" id="lastname" name="register[lastname]" value="'.$_POST['register']['lastname'].'" /></div>
			<label for="company">'.$this->l('Company').'</label>
			<div class="margin-form"><input type="text" size="33" id="company" name="register[company]" value="'.$_POST['register']['company'].'" /></div>
			<label for="registration_country">'.$this->l('Registration country').'</label>
			<div class="margin-form">
				<select name="register[registration_country]" id="registration_country">
                    <option '.($_POST['register']['registration_country']=="" ? 'selected="true"' : '').' value=""></option>
                    <option '.($_POST['register']['registration_country']=="AF" ? 'selected="true"' : '').' value="AF">Afghanistan</option>
					<option '.($_POST['register']['registration_country']=="AL" ? 'selected="true"' : '').' value="AL">Albania</option>
					<option '.($_POST['register']['registration_country']=="AS" ? 'selected="true"' : '').' value="AS">American Samoa</option>
					<option '.($_POST['register']['registration_country']=="AD" ? 'selected="true"' : '').' value="AD">Andorra</option>
					<option '.($_POST['register']['registration_country']=="AO" ? 'selected="true"' : '').' value="AO">Angola</option>
					<option '.($_POST['register']['registration_country']=="AI" ? 'selected="true"' : '').' value="AI">Anguilla</option>
					<option '.($_POST['register']['registration_country']=="AQ" ? 'selected="true"' : '').' value="AQ">Antarctica</option>
					<option '.($_POST['register']['registration_country']=="AG" ? 'selected="true"' : '').' value="AG">Antigua And Barbuda</option>
					<option '.($_POST['register']['registration_country']=="AR" ? 'selected="true"' : '').' value="AR">Argentina</option>
					<option '.($_POST['register']['registration_country']=="AM" ? 'selected="true"' : '').' value="AM">Armenia</option>
					<option '.($_POST['register']['registration_country']=="AW" ? 'selected="true"' : '').' value="AW">Aruba</option>
					<option '.($_POST['register']['registration_country']=="AU" ? 'selected="true"' : '').' value="AU">Australia</option>
					<option '.($_POST['register']['registration_country']=="AT" ? 'selected="true"' : '').' value="AT">Austria</option>
					<option '.($_POST['register']['registration_country']=="AZ" ? 'selected="true"' : '').' value="AZ">Azerbaijan</option>
					<option '.($_POST['register']['registration_country']=="BS" ? 'selected="true"' : '').' value="BS">Bahamas</option>
					<option '.($_POST['register']['registration_country']=="BH" ? 'selected="true"' : '').' value="BH">Bahrain</option>
					<option '.($_POST['register']['registration_country']=="BD" ? 'selected="true"' : '').' value="BD">Bangladesh</option>
					<option '.($_POST['register']['registration_country']=="BB" ? 'selected="true"' : '').' value="BB">Barbados</option>
					<option '.($_POST['register']['registration_country']=="BY" ? 'selected="true"' : '').' value="BY">Belarus</option>
					<option '.($_POST['register']['registration_country']=="BE" ? 'selected="true"' : '').' value="BE">Belgium</option>
					<option '.($_POST['register']['registration_country']=="BZ" ? 'selected="true"' : '').' value="BZ">Belize</option>
					<option '.($_POST['register']['registration_country']=="BJ" ? 'selected="true"' : '').' value="BJ">Benin</option>
					<option '.($_POST['register']['registration_country']=="BM" ? 'selected="true"' : '').' value="BM">Bermuda</option>
					<option '.($_POST['register']['registration_country']=="BT" ? 'selected="true"' : '').' value="BT">Bhutan</option>
					<option '.($_POST['register']['registration_country']=="BO" ? 'selected="true"' : '').' value="BO">Bolivia</option>
					<option '.($_POST['register']['registration_country']=="BA" ? 'selected="true"' : '').' value="BA">Bosnia And Herzegovina</option>
					<option '.($_POST['register']['registration_country']=="BW" ? 'selected="true"' : '').' value="BW">Botswana</option>
					<option '.($_POST['register']['registration_country']=="BV" ? 'selected="true"' : '').' value="BV">Bouvet Island</option>
					<option '.($_POST['register']['registration_country']=="BR" ? 'selected="true"' : '').' value="BR">Brazil</option>
					<option '.($_POST['register']['registration_country']=="IO" ? 'selected="true"' : '').' value="IO">British Indian Ocean Territory</option>
					<option '.($_POST['register']['registration_country']=="BN" ? 'selected="true"' : '').' value="BN">Brunei Darussalam</option>
					<option '.($_POST['register']['registration_country']=="BG" ? 'selected="true"' : '').' value="BG">Bulgaria</option>
					<option '.($_POST['register']['registration_country']=="BF" ? 'selected="true"' : '').' value="BF">Burkina Faso</option>
					<option '.($_POST['register']['registration_country']=="BI" ? 'selected="true"' : '').' value="BI">Burundi</option>
					<option '.($_POST['register']['registration_country']=="KF" ? 'selected="true"' : '').' value="KF">Cambodia</option>
					<option '.($_POST['register']['registration_country']=="CM" ? 'selected="true"' : '').' value="CM">Cameroon</option>
					<option '.($_POST['register']['registration_country']=="CA" ? 'selected="true"' : '').' value="CA">Canada</option>
					<option '.($_POST['register']['registration_country']=="CV" ? 'selected="true"' : '').' value="CV">Cape Verde</option>
					<option '.($_POST['register']['registration_country']=="KY" ? 'selected="true"' : '').' value="KY">Cayman Islands</option>
					<option '.($_POST['register']['registration_country']=="CF" ? 'selected="true"' : '').' value="CF">Central African Republic</option>
					<option '.($_POST['register']['registration_country']=="TD" ? 'selected="true"' : '').' value="TD">Chad</option>
					<option '.($_POST['register']['registration_country']=="CL" ? 'selected="true"' : '').' value="CL">Chile</option>
					<option '.($_POST['register']['registration_country']=="CN" ? 'selected="true"' : '').' value="CN">China</option>
					<option '.($_POST['register']['registration_country']=="CX" ? 'selected="true"' : '').' value="CX">Christmas Island</option>
					<option '.($_POST['register']['registration_country']=="CC" ? 'selected="true"' : '').' value="CC">Cocos (keeling) Islands</option>
					<option '.($_POST['register']['registration_country']=="CO" ? 'selected="true"' : '').' value="CO">Colombia</option>
					<option '.($_POST['register']['registration_country']=="KM" ? 'selected="true"' : '').' value="KM">Comoros</option>
					<option '.($_POST['register']['registration_country']=="CG" ? 'selected="true"' : '').' value="CG">Congo</option>
					<option '.($_POST['register']['registration_country']=="CD" ? 'selected="true"' : '').' value="CD">Congo, The Democratic Republic Of The</option>
					<option '.($_POST['register']['registration_country']=="CK" ? 'selected="true"' : '').' value="CK">Cook Islands</option>
					<option '.($_POST['register']['registration_country']=="CR" ? 'selected="true"' : '').' value="CR">Costa Rica</option>
					<option '.($_POST['register']['registration_country']=="CI" ? 'selected="true"' : '').' value="CI">Cote D&rsquo;ivoire</option>
					<option '.($_POST['register']['registration_country']=="HR" ? 'selected="true"' : '').' value="HR">Croatia</option>
					<option '.($_POST['register']['registration_country']=="CU" ? 'selected="true"' : '').' value="CU">Cuba</option>
					<option '.($_POST['register']['registration_country']=="CY" ? 'selected="true"' : '').' value="CY">Cyprus</option>
					<option '.($_POST['register']['registration_country']=="CZ" ? 'selected="true"' : '').' value="CZ">Czech Republic</option>
					<option '.($_POST['register']['registration_country']=="DK" ? 'selected="true"' : '').' value="DK">Denmark</option>
					<option '.($_POST['register']['registration_country']=="DJ" ? 'selected="true"' : '').' value="DJ">Djibouti</option>
					<option '.($_POST['register']['registration_country']=="DM" ? 'selected="true"' : '').' value="DM">Dominica</option>
					<option '.($_POST['register']['registration_country']=="DO" ? 'selected="true"' : '').' value="DO">Dominican Republic</option>
					<option '.($_POST['register']['registration_country']=="EC" ? 'selected="true"' : '').' value="EC">Ecuador</option>
					<option '.($_POST['register']['registration_country']=="EG" ? 'selected="true"' : '').' value="EG">Egypt</option>
					<option '.($_POST['register']['registration_country']=="SV" ? 'selected="true"' : '').' value="SV">El Salvador</option>
					<option '.($_POST['register']['registration_country']=="GQ" ? 'selected="true"' : '').' value="GQ">Equatorial Guinea</option>
					<option '.($_POST['register']['registration_country']=="ER" ? 'selected="true"' : '').' value="ER">Eritrea</option>
					<option '.($_POST['register']['registration_country']=="EE" ? 'selected="true"' : '').' value="EE">Estonia</option>
					<option '.($_POST['register']['registration_country']=="ET" ? 'selected="true"' : '').' value="ET">Ethiopia</option>
					<option '.($_POST['register']['registration_country']=="FK" ? 'selected="true"' : '').' value="FK">Falkland Islands (malvinas)</option>
					<option '.($_POST['register']['registration_country']=="FO" ? 'selected="true"' : '').' value="FO">Faroe Islands</option>
					<option '.($_POST['register']['registration_country']=="FJ" ? 'selected="true"' : '').' value="FJ">Fiji</option>
					<option '.($_POST['register']['registration_country']=="FI" ? 'selected="true"' : '').' value="FI">Finland</option>
					<option '.($_POST['register']['registration_country']=="FR" ? 'selected="true"' : '').' value="FR">France</option>
					<option '.($_POST['register']['registration_country']=="FX" ? 'selected="true"' : '').' value="FX">France, Metropolitan</option>
					<option '.($_POST['register']['registration_country']=="GF" ? 'selected="true"' : '').' value="GF">French Guiana</option>
					<option '.($_POST['register']['registration_country']=="PF" ? 'selected="true"' : '').' value="PF">French Polynesia</option>
					<option '.($_POST['register']['registration_country']=="TF" ? 'selected="true"' : '').' value="TF">French Southern Territories</option>
					<option '.($_POST['register']['registration_country']=="GA" ? 'selected="true"' : '').' value="GA">Gabon</option>
					<option '.($_POST['register']['registration_country']=="GM" ? 'selected="true"' : '').' value="GM">Gambia</option>
					<option '.($_POST['register']['registration_country']=="GE" ? 'selected="true"' : '').' value="GE">Georgia</option>
					<option '.($_POST['register']['registration_country']=="DE" ? 'selected="true"' : '').' value="DE">Germany</option>
					<option '.($_POST['register']['registration_country']=="GH" ? 'selected="true"' : '').' value="GH">Ghana</option>
					<option '.($_POST['register']['registration_country']=="GI" ? 'selected="true"' : '').' value="GI">Gibraltar</option>
					<option '.($_POST['register']['registration_country']=="GR" ? 'selected="true"' : '').' value="GR">Greece</option>
					<option '.($_POST['register']['registration_country']=="GL" ? 'selected="true"' : '').' value="GL">Greenland</option>
					<option '.($_POST['register']['registration_country']=="GD" ? 'selected="true"' : '').' value="GD">Grenada</option>
					<option '.($_POST['register']['registration_country']=="GP" ? 'selected="true"' : '').' value="GP">Guadeloupe</option>
					<option '.($_POST['register']['registration_country']=="GU" ? 'selected="true"' : '').' value="GU">Guam</option>
					<option '.($_POST['register']['registration_country']=="GT" ? 'selected="true"' : '').' value="GT">Guatemala</option>
					<option '.($_POST['register']['registration_country']=="GG" ? 'selected="true"' : '').' value="GG">Guernsey</option>
					<option '.($_POST['register']['registration_country']=="GN" ? 'selected="true"' : '').' value="GN">Guinea</option>
					<option '.($_POST['register']['registration_country']=="GW" ? 'selected="true"' : '').' value="GW">Guinea-bissau</option>
					<option '.($_POST['register']['registration_country']=="GY" ? 'selected="true"' : '').' value="GY">Guyana</option>
					<option '.($_POST['register']['registration_country']=="HT" ? 'selected="true"' : '').' value="HT">Haiti</option>
					<option '.($_POST['register']['registration_country']=="HM" ? 'selected="true"' : '').' value="HM">Heard Island And Mcdonald Islands</option>
					<option '.($_POST['register']['registration_country']=="VA" ? 'selected="true"' : '').' value="VA">Holy See (vatican City State)</option>
					<option '.($_POST['register']['registration_country']=="HN" ? 'selected="true"' : '').' value="HN">Honduras</option>
					<option '.($_POST['register']['registration_country']=="HK" ? 'selected="true"' : '').' value="HK">Hong Kong</option>
					<option '.($_POST['register']['registration_country']=="HU" ? 'selected="true"' : '').' value="HU">Hungary</option>
					<option '.($_POST['register']['registration_country']=="IS" ? 'selected="true"' : '').' value="IS">Iceland</option>
					<option '.($_POST['register']['registration_country']=="IN" ? 'selected="true"' : '').' value="IN">India</option>
					<option '.($_POST['register']['registration_country']=="IR" ? 'selected="true"' : '').' value="IR">Iran, Islamic Republic Of</option>
					<option '.($_POST['register']['registration_country']=="IQ" ? 'selected="true"' : '').' value="IQ">Iraq</option>
					<option '.($_POST['register']['registration_country']=="IE" ? 'selected="true"' : '').' value="IE">Ireland</option>
					<option '.($_POST['register']['registration_country']=="IM" ? 'selected="true"' : '').' value="IM">Isle of man</option>
					<option '.($_POST['register']['registration_country']=="IL" ? 'selected="true"' : '').' value="IL">Israel</option>
					<option '.($_POST['register']['registration_country']=="IT" ? 'selected="true"' : '').' value="IT">Italy</option>
					<option '.($_POST['register']['registration_country']=="JM" ? 'selected="true"' : '').' value="JM">Jamaica</option>
					<option '.($_POST['register']['registration_country']=="JP" ? 'selected="true"' : '').' value="JP">Japan</option>
					<option '.($_POST['register']['registration_country']=="JE" ? 'selected="true"' : '').' value="JE">Jersey</option>
					<option '.($_POST['register']['registration_country']=="JO" ? 'selected="true"' : '').' value="JO">Jordan</option>
					<option '.($_POST['register']['registration_country']=="KZ" ? 'selected="true"' : '').' value="KZ">Kazakhstan</option>
					<option '.($_POST['register']['registration_country']=="KE" ? 'selected="true"' : '').' value="KE">Kenya</option>
					<option '.($_POST['register']['registration_country']=="KI" ? 'selected="true"' : '').' value="KI">Kiribati</option>
					<option '.($_POST['register']['registration_country']=="KW" ? 'selected="true"' : '').' value="KW">Kuwait</option>
					<option '.($_POST['register']['registration_country']=="KG" ? 'selected="true"' : '').' value="KG">Kyrgyzstan</option>
					<option '.($_POST['register']['registration_country']=="LA" ? 'selected="true"' : '').' value="LA">Lao People&rsquo;s Democratic Republic</option>
					<option '.($_POST['register']['registration_country']=="LV" ? 'selected="true"' : '').' value="LV">Latvia</option>
					<option '.($_POST['register']['registration_country']=="LB" ? 'selected="true"' : '').' value="LB">Lebanon</option>
					<option '.($_POST['register']['registration_country']=="LS" ? 'selected="true"' : '').' value="LS">Lesotho</option>
					<option '.($_POST['register']['registration_country']=="LR" ? 'selected="true"' : '').' value="LR">Liberia</option>
					<option '.($_POST['register']['registration_country']=="LY" ? 'selected="true"' : '').' value="LY">Libyan Arab Jamahiriya</option>
					<option '.($_POST['register']['registration_country']=="LI" ? 'selected="true"' : '').' value="LI">Liechtenstein</option>
					<option '.($_POST['register']['registration_country']=="LT" ? 'selected="true"' : '').' value="LT">Lithuania</option>
					<option '.($_POST['register']['registration_country']=="LU" ? 'selected="true"' : '').' value="LU">Luxembourg</option>
					<option '.($_POST['register']['registration_country']=="MO" ? 'selected="true"' : '').' value="MO">Macao</option>
					<option '.($_POST['register']['registration_country']=="MK" ? 'selected="true"' : '').' value="MK">Macedonia, The Former Yugoslav Republic Of</option>
					<option '.($_POST['register']['registration_country']=="MG" ? 'selected="true"' : '').' value="MG">Madagascar</option>
					<option '.($_POST['register']['registration_country']=="MW" ? 'selected="true"' : '').' value="MW">Malawi</option>
					<option '.($_POST['register']['registration_country']=="MY" ? 'selected="true"' : '').' value="MY">Malaysia</option>
					<option '.($_POST['register']['registration_country']=="MV" ? 'selected="true"' : '').' value="MV">Maldives</option>
					<option '.($_POST['register']['registration_country']=="ML" ? 'selected="true"' : '').' value="ML">Mali</option>
					<option '.($_POST['register']['registration_country']=="MT" ? 'selected="true"' : '').' value="MT">Malta</option>
					<option '.($_POST['register']['registration_country']=="MH" ? 'selected="true"' : '').' value="MH">Marshall Islands</option>
					<option '.($_POST['register']['registration_country']=="MQ" ? 'selected="true"' : '').' value="MQ">Martinique</option>
					<option '.($_POST['register']['registration_country']=="MR" ? 'selected="true"' : '').' value="MR">Mauritania</option>
					<option '.($_POST['register']['registration_country']=="MU" ? 'selected="true"' : '').' value="MU">Mauritius</option>
					<option '.($_POST['register']['registration_country']=="YT" ? 'selected="true"' : '').' value="YT">Mayotte</option>
					<option '.($_POST['register']['registration_country']=="MX" ? 'selected="true"' : '').' value="MX">Mexico</option>
					<option '.($_POST['register']['registration_country']=="FM" ? 'selected="true"' : '').' value="FM">Micronesia, Federated States Of</option>
					<option '.($_POST['register']['registration_country']=="MD" ? 'selected="true"' : '').' value="MD">Moldova, Republic Of</option>
					<option '.($_POST['register']['registration_country']=="MC" ? 'selected="true"' : '').' value="MC">Monaco</option>
					<option '.($_POST['register']['registration_country']=="MN" ? 'selected="true"' : '').' value="MN">Mongolia</option>
					<option '.($_POST['register']['registration_country']=="MS" ? 'selected="true"' : '').' value="MS">Montserrat</option>
					<option '.($_POST['register']['registration_country']=="MZ" ? 'selected="true"' : '').' value="MZ">Mozambique</option>
					<option '.($_POST['register']['registration_country']=="MM" ? 'selected="true"' : '').' value="MM">Myanmar</option>
					<option '.($_POST['register']['registration_country']=="NA" ? 'selected="true"' : '').' value="NA">Namibia</option>
					<option '.($_POST['register']['registration_country']=="NR" ? 'selected="true"' : '').' value="NR">Nauru</option>
					<option '.($_POST['register']['registration_country']=="NP" ? 'selected="true"' : '').' value="NP">Nepal</option>
					<option '.($_POST['register']['registration_country']=="NL" ? 'selected="true"' : '').' value="NL">Netherlands</option>
					<option '.($_POST['register']['registration_country']=="AN" ? 'selected="true"' : '').' value="AN">Netherlands Antilles</option>
					<option '.($_POST['register']['registration_country']=="NC" ? 'selected="true"' : '').' value="NC">New Caledonia</option>
					<option '.($_POST['register']['registration_country']=="NZ" ? 'selected="true"' : '').' value="NZ">New Zealand</option>
					<option '.($_POST['register']['registration_country']=="NI" ? 'selected="true"' : '').' value="NI">Nicaragua</option>
					<option '.($_POST['register']['registration_country']=="NE" ? 'selected="true"' : '').' value="NE">Niger</option>
					<option '.($_POST['register']['registration_country']=="NU" ? 'selected="true"' : '').' value="NU">Niue</option>
					<option '.($_POST['register']['registration_country']=="NF" ? 'selected="true"' : '').' value="NF">Norfolk Island</option>
					<option '.($_POST['register']['registration_country']=="MP" ? 'selected="true"' : '').' value="MP">Northern Mariana Islands</option>
					<option '.($_POST['register']['registration_country']=="NO" ? 'selected="true"' : '').' value="NO">Norway</option>
					<option '.($_POST['register']['registration_country']=="OM" ? 'selected="true"' : '').' value="OM">Oman</option>
					<option '.($_POST['register']['registration_country']=="PK" ? 'selected="true"' : '').' value="PK">Pakistan</option>
					<option '.($_POST['register']['registration_country']=="PW" ? 'selected="true"' : '').' value="PW">Palau</option>
					<option '.($_POST['register']['registration_country']=="PS" ? 'selected="true"' : '').' value="PS">Palestinian Territory, Occupied</option>
					<option '.($_POST['register']['registration_country']=="PA" ? 'selected="true"' : '').' value="PA">Panama</option>
					<option '.($_POST['register']['registration_country']=="PG" ? 'selected="true"' : '').' value="PG">Papua New Guinea</option>
					<option '.($_POST['register']['registration_country']=="PY" ? 'selected="true"' : '').' value="PY">Paraguay</option>
					<option '.($_POST['register']['registration_country']=="PE" ? 'selected="true"' : '').' value="PE">Peru</option>
					<option '.($_POST['register']['registration_country']=="PH" ? 'selected="true"' : '').' value="PH">Philippines</option>
					<option '.($_POST['register']['registration_country']=="PN" ? 'selected="true"' : '').' value="PN">Pitcairn</option>
					<option '.($_POST['register']['registration_country']=="PL" ? 'selected="true"' : '').' value="PL">Poland</option>
					<option '.($_POST['register']['registration_country']=="PT" ? 'selected="true"' : '').' value="PT">Portugal</option>
					<option '.($_POST['register']['registration_country']=="PR" ? 'selected="true"' : '').' value="PR">Puerto Rico</option>
					<option '.($_POST['register']['registration_country']=="QA" ? 'selected="true"' : '').' value="QA">Qatar</option>
					<option '.($_POST['register']['registration_country']=="ME" ? 'selected="true"' : '').' value="ME">Republic of Montenegro</option>
					<option '.($_POST['register']['registration_country']=="RS" ? 'selected="true"' : '').' value="RS">Republic of Serbia</option>
					<option '.($_POST['register']['registration_country']=="RE" ? 'selected="true"' : '').' value="RE">Reunion</option>
					<option '.($_POST['register']['registration_country']=="RO" ? 'selected="true"' : '').' value="RO">Romania</option>
					<option '.($_POST['register']['registration_country']=="RU" ? 'selected="true"' : '').' value="RU">Russian Federation</option>
					<option '.($_POST['register']['registration_country']=="RW" ? 'selected="true"' : '').' value="RW">Rwanda</option>
					<option '.($_POST['register']['registration_country']=="SH" ? 'selected="true"' : '').' value="SH">Saint Helena</option>
					<option '.($_POST['register']['registration_country']=="KN" ? 'selected="true"' : '').' value="KN">Saint Kitts And Nevis</option>
					<option '.($_POST['register']['registration_country']=="LC" ? 'selected="true"' : '').' value="LC">Saint Lucia</option>
					<option '.($_POST['register']['registration_country']=="PM" ? 'selected="true"' : '').' value="PM">Saint Pierre And Miquelon</option>
					<option '.($_POST['register']['registration_country']=="VC" ? 'selected="true"' : '').' value="VC">Saint Vincent And The Grenadines</option>
					<option '.($_POST['register']['registration_country']=="WS" ? 'selected="true"' : '').' value="WS">Samoa</option>
					<option '.($_POST['register']['registration_country']=="SM" ? 'selected="true"' : '').' value="SM">San Marino</option>
					<option '.($_POST['register']['registration_country']=="ST" ? 'selected="true"' : '').' value="ST">Sao Tome And Principe</option>
					<option '.($_POST['register']['registration_country']=="SA" ? 'selected="true"' : '').' value="SA">Saudi Arabia</option>
					<option '.($_POST['register']['registration_country']=="SN" ? 'selected="true"' : '').' value="SN">Senegal</option>
					<option '.($_POST['register']['registration_country']=="CS" ? 'selected="true"' : '').' value="CS">Seychelles</option>
					<option '.($_POST['register']['registration_country']=="SC" ? 'selected="true"' : '').' value="SC">Sierra Leone</option>
					<option '.($_POST['register']['registration_country']=="SG" ? 'selected="true"' : '').' value="SG">Singapore</option>
					<option '.($_POST['register']['registration_country']=="SK" ? 'selected="true"' : '').' value="SK">Slovakia</option>
					<option '.($_POST['register']['registration_country']=="SI" ? 'selected="true"' : '').' value="SI">Slovenia</option>
					<option '.($_POST['register']['registration_country']=="SB" ? 'selected="true"' : '').' value="SB">Solomon Islands</option>
					<option '.($_POST['register']['registration_country']=="SO" ? 'selected="true"' : '').' value="SO">Somalia</option>
					<option '.($_POST['register']['registration_country']=="ZA" ? 'selected="true"' : '').' value="ZA">South Africa</option>
					<option '.($_POST['register']['registration_country']=="GS" ? 'selected="true"' : '').' value="GS">South Georgia And The South Sandwich Islands</option>
					<option '.($_POST['register']['registration_country']=="ES" ? 'selected="true"' : '').' value="ES">Spain</option>
					<option '.($_POST['register']['registration_country']=="LK" ? 'selected="true"' : '').' value="LK">Sri Lanka</option>
					<option '.($_POST['register']['registration_country']=="SD" ? 'selected="true"' : '').' value="SD">Sudan</option>
					<option '.($_POST['register']['registration_country']=="SR" ? 'selected="true"' : '').' value="SR">Suriname</option>
					<option '.($_POST['register']['registration_country']=="SJ" ? 'selected="true"' : '').' value="SJ">Svalbard And Jan Mayen</option>
					<option '.($_POST['register']['registration_country']=="SZ" ? 'selected="true"' : '').' value="SZ">Swaziland</option>
					<option '.($_POST['register']['registration_country']=="SE" ? 'selected="true"' : '').' value="SE">Sweden</option>
					<option '.($_POST['register']['registration_country']=="CH" ? 'selected="true"' : '').' value="CH">Switzerland</option>
					<option '.($_POST['register']['registration_country']=="SY" ? 'selected="true"' : '').' value="SY">Syrian Arab Republic</option>
					<option '.($_POST['register']['registration_country']=="TW" ? 'selected="true"' : '').' value="TW">Taiwan, Province Of China</option>
					<option '.($_POST['register']['registration_country']=="TJ" ? 'selected="true"' : '').' value="TJ">Tajikistan</option>
					<option '.($_POST['register']['registration_country']=="TZ" ? 'selected="true"' : '').' value="TZ">Tanzania, United Republic Of</option>
					<option '.($_POST['register']['registration_country']=="TH" ? 'selected="true"' : '').' value="TH">Thailand</option>
					<option '.($_POST['register']['registration_country']=="TL" ? 'selected="true"' : '').' value="TL">Timor-leste</option>
					<option '.($_POST['register']['registration_country']=="TG" ? 'selected="true"' : '').' value="TG">Togo</option>
					<option '.($_POST['register']['registration_country']=="TK" ? 'selected="true"' : '').' value="TK">Tokelau</option>
					<option '.($_POST['register']['registration_country']=="TO" ? 'selected="true"' : '').' value="TO">Tonga</option>
					<option '.($_POST['register']['registration_country']=="TI" ? 'selected="true"' : '').' value="TT">Trinidad And Tobago</option>
					<option '.($_POST['register']['registration_country']=="TN" ? 'selected="true"' : '').' value="TN">Tunisia</option>
					<option '.($_POST['register']['registration_country']=="TR" ? 'selected="true"' : '').' value="TR">Turkey</option>
					<option '.($_POST['register']['registration_country']=="TM" ? 'selected="true"' : '').' value="TM">Turkmenistan</option>
					<option '.($_POST['register']['registration_country']=="TC" ? 'selected="true"' : '').' value="TC">Turks And Caicos Islands</option>
					<option '.($_POST['register']['registration_country']=="TV" ? 'selected="true"' : '').' value="TV">Tuvalu</option>
					<option '.($_POST['register']['registration_country']=="UG" ? 'selected="true"' : '').' value="UG">Uganda</option>
					<option '.($_POST['register']['registration_country']=="UA" ? 'selected="true"' : '').' value="UA">Ukraine</option>
					<option '.($_POST['register']['registration_country']=="AE" ? 'selected="true"' : '').' value="AE">United Arab Emirates</option>
					<option '.($_POST['register']['registration_country']=="GB" ? 'selected="true"' : '').' value="GB">United Kingdom</option>
					<option '.($_POST['register']['registration_country']=="US" ? 'selected="true"' : '').' value="US">United States</option>
					<option '.($_POST['register']['registration_country']=="UM" ? 'selected="true"' : '').' value="UM">United States Minor Outlying Islands</option>
					<option '.($_POST['register']['registration_country']=="UY" ? 'selected="true"' : '').' value="UY">Uruguay</option>
					<option '.($_POST['register']['registration_country']=="UZ" ? 'selected="true"' : '').' value="UZ">Uzbekistan</option>
					<option '.($_POST['register']['registration_country']=="VU" ? 'selected="true"' : '').' value="VU">Vanuatu</option>
					<option '.($_POST['register']['registration_country']=="VE" ? 'selected="true"' : '').' value="VE">Venezuela</option>
					<option '.($_POST['register']['registration_country']=="VG" ? 'selected="true"' : '').' value="VG">Virgin Islands, British</option>
					<option '.($_POST['register']['registration_country']=="VI" ? 'selected="true"' : '').' value="VI">Virgin Islands, U.s.</option>
					<option '.($_POST['register']['registration_country']=="WF" ? 'selected="true"' : '').' value="WF">Wallis And Futuna</option>
					<option '.($_POST['register']['registration_country']=="EH" ? 'selected="true"' : '').' value="EH">Western Sahara</option>
					<option '.($_POST['register']['registration_country']=="YE" ? 'selected="true"' : '').' value="YE">Yemen</option>
					<option '.($_POST['register']['registration_country']=="YU" ? 'selected="true"' : '').' value="YU">Yugoslavia</option>
					<option '.($_POST['register']['registration_country']=="ZM" ? 'selected="true"' : '').' value="ZM">Zambia</option>
					<option '.($_POST['register']['registration_country']=="ZW" ? 'selected="true"' : '').' value="ZW">Zimbabwe</option>
				</select>
			</div>
			<label for="director_country">'.$this->l('Director&rsquo;s country of residence').'</label>
			<div class="margin-form">
				<select name="register[director_country]" id="director_country">
                    <option '.($_POST['register']['director_country']=="" ? 'selected="true"' : '').' value=""></option>
                    <option '.($_POST['register']['director_country']=="AF" ? 'selected="true"' : '').' value="AF">Afghanistan</option>
					<option '.($_POST['register']['director_country']=="AL" ? 'selected="true"' : '').' value="AL">Albania</option>
					<option '.($_POST['register']['director_country']=="AS" ? 'selected="true"' : '').' value="AS">American Samoa</option>
					<option '.($_POST['register']['director_country']=="AD" ? 'selected="true"' : '').' value="AD">Andorra</option>
					<option '.($_POST['register']['director_country']=="AO" ? 'selected="true"' : '').' value="AO">Angola</option>
					<option '.($_POST['register']['director_country']=="AI" ? 'selected="true"' : '').' value="AI">Anguilla</option>
					<option '.($_POST['register']['director_country']=="AQ" ? 'selected="true"' : '').' value="AQ">Antarctica</option>
					<option '.($_POST['register']['director_country']=="AG" ? 'selected="true"' : '').' value="AG">Antigua And Barbuda</option>
					<option '.($_POST['register']['director_country']=="AR" ? 'selected="true"' : '').' value="AR">Argentina</option>
					<option '.($_POST['register']['director_country']=="AM" ? 'selected="true"' : '').' value="AM">Armenia</option>
					<option '.($_POST['register']['director_country']=="AW" ? 'selected="true"' : '').' value="AW">Aruba</option>
					<option '.($_POST['register']['director_country']=="AU" ? 'selected="true"' : '').' value="AU">Australia</option>
					<option '.($_POST['register']['director_country']=="AT" ? 'selected="true"' : '').' value="AT">Austria</option>
					<option '.($_POST['register']['director_country']=="AZ" ? 'selected="true"' : '').' value="AZ">Azerbaijan</option>
					<option '.($_POST['register']['director_country']=="BS" ? 'selected="true"' : '').' value="BS">Bahamas</option>
					<option '.($_POST['register']['director_country']=="BH" ? 'selected="true"' : '').' value="BH">Bahrain</option>
					<option '.($_POST['register']['director_country']=="BD" ? 'selected="true"' : '').' value="BD">Bangladesh</option>
					<option '.($_POST['register']['director_country']=="BB" ? 'selected="true"' : '').' value="BB">Barbados</option>
					<option '.($_POST['register']['director_country']=="BY" ? 'selected="true"' : '').' value="BY">Belarus</option>
					<option '.($_POST['register']['director_country']=="BE" ? 'selected="true"' : '').' value="BE">Belgium</option>
					<option '.($_POST['register']['director_country']=="BZ" ? 'selected="true"' : '').' value="BZ">Belize</option>
					<option '.($_POST['register']['director_country']=="BJ" ? 'selected="true"' : '').' value="BJ">Benin</option>
					<option '.($_POST['register']['director_country']=="BM" ? 'selected="true"' : '').' value="BM">Bermuda</option>
					<option '.($_POST['register']['director_country']=="BT" ? 'selected="true"' : '').' value="BT">Bhutan</option>
					<option '.($_POST['register']['director_country']=="BO" ? 'selected="true"' : '').' value="BO">Bolivia</option>
					<option '.($_POST['register']['director_country']=="BA" ? 'selected="true"' : '').' value="BA">Bosnia And Herzegovina</option>
					<option '.($_POST['register']['director_country']=="BW" ? 'selected="true"' : '').' value="BW">Botswana</option>
					<option '.($_POST['register']['director_country']=="BV" ? 'selected="true"' : '').' value="BV">Bouvet Island</option>
					<option '.($_POST['register']['director_country']=="BR" ? 'selected="true"' : '').' value="BR">Brazil</option>
					<option '.($_POST['register']['director_country']=="IO" ? 'selected="true"' : '').' value="IO">British Indian Ocean Territory</option>
					<option '.($_POST['register']['director_country']=="BN" ? 'selected="true"' : '').' value="BN">Brunei Darussalam</option>
					<option '.($_POST['register']['director_country']=="BG" ? 'selected="true"' : '').' value="BG">Bulgaria</option>
					<option '.($_POST['register']['director_country']=="BF" ? 'selected="true"' : '').' value="BF">Burkina Faso</option>
					<option '.($_POST['register']['director_country']=="BI" ? 'selected="true"' : '').' value="BI">Burundi</option>
					<option '.($_POST['register']['director_country']=="KF" ? 'selected="true"' : '').' value="KF">Cambodia</option>
					<option '.($_POST['register']['director_country']=="CM" ? 'selected="true"' : '').' value="CM">Cameroon</option>
					<option '.($_POST['register']['director_country']=="CA" ? 'selected="true"' : '').' value="CA">Canada</option>
					<option '.($_POST['register']['director_country']=="CV" ? 'selected="true"' : '').' value="CV">Cape Verde</option>
					<option '.($_POST['register']['director_country']=="KY" ? 'selected="true"' : '').' value="KY">Cayman Islands</option>
					<option '.($_POST['register']['director_country']=="CF" ? 'selected="true"' : '').' value="CF">Central African Republic</option>
					<option '.($_POST['register']['director_country']=="TD" ? 'selected="true"' : '').' value="TD">Chad</option>
					<option '.($_POST['register']['director_country']=="CL" ? 'selected="true"' : '').' value="CL">Chile</option>
					<option '.($_POST['register']['director_country']=="CN" ? 'selected="true"' : '').' value="CN">China</option>
					<option '.($_POST['register']['director_country']=="CX" ? 'selected="true"' : '').' value="CX">Christmas Island</option>
					<option '.($_POST['register']['director_country']=="CC" ? 'selected="true"' : '').' value="CC">Cocos (keeling) Islands</option>
					<option '.($_POST['register']['director_country']=="CO" ? 'selected="true"' : '').' value="CO">Colombia</option>
					<option '.($_POST['register']['director_country']=="KM" ? 'selected="true"' : '').' value="KM">Comoros</option>
					<option '.($_POST['register']['director_country']=="CG" ? 'selected="true"' : '').' value="CG">Congo</option>
					<option '.($_POST['register']['director_country']=="CD" ? 'selected="true"' : '').' value="CD">Congo, The Democratic Republic Of The</option>
					<option '.($_POST['register']['director_country']=="CK" ? 'selected="true"' : '').' value="CK">Cook Islands</option>
					<option '.($_POST['register']['director_country']=="CR" ? 'selected="true"' : '').' value="CR">Costa Rica</option>
					<option '.($_POST['register']['director_country']=="CI" ? 'selected="true"' : '').' value="CI">Cote D&rsquo;ivoire</option>
					<option '.($_POST['register']['director_country']=="HR" ? 'selected="true"' : '').' value="HR">Croatia</option>
					<option '.($_POST['register']['director_country']=="CU" ? 'selected="true"' : '').' value="CU">Cuba</option>
					<option '.($_POST['register']['director_country']=="CY" ? 'selected="true"' : '').' value="CY">Cyprus</option>
					<option '.($_POST['register']['director_country']=="CZ" ? 'selected="true"' : '').' value="CZ">Czech Republic</option>
					<option '.($_POST['register']['director_country']=="DK" ? 'selected="true"' : '').' value="DK">Denmark</option>
					<option '.($_POST['register']['director_country']=="DJ" ? 'selected="true"' : '').' value="DJ">Djibouti</option>
					<option '.($_POST['register']['director_country']=="DM" ? 'selected="true"' : '').' value="DM">Dominica</option>
					<option '.($_POST['register']['director_country']=="DO" ? 'selected="true"' : '').' value="DO">Dominican Republic</option>
					<option '.($_POST['register']['director_country']=="EC" ? 'selected="true"' : '').' value="EC">Ecuador</option>
					<option '.($_POST['register']['director_country']=="EG" ? 'selected="true"' : '').' value="EG">Egypt</option>
					<option '.($_POST['register']['director_country']=="SV" ? 'selected="true"' : '').' value="SV">El Salvador</option>
					<option '.($_POST['register']['director_country']=="GQ" ? 'selected="true"' : '').' value="GQ">Equatorial Guinea</option>
					<option '.($_POST['register']['director_country']=="ER" ? 'selected="true"' : '').' value="ER">Eritrea</option>
					<option '.($_POST['register']['director_country']=="EE" ? 'selected="true"' : '').' value="EE">Estonia</option>
					<option '.($_POST['register']['director_country']=="ET" ? 'selected="true"' : '').' value="ET">Ethiopia</option>
					<option '.($_POST['register']['director_country']=="FK" ? 'selected="true"' : '').' value="FK">Falkland Islands (malvinas)</option>
					<option '.($_POST['register']['director_country']=="FO" ? 'selected="true"' : '').' value="FO">Faroe Islands</option>
					<option '.($_POST['register']['director_country']=="FJ" ? 'selected="true"' : '').' value="FJ">Fiji</option>
					<option '.($_POST['register']['director_country']=="FI" ? 'selected="true"' : '').' value="FI">Finland</option>
					<option '.($_POST['register']['director_country']=="FR" ? 'selected="true"' : '').' value="FR">France</option>
					<option '.($_POST['register']['director_country']=="FX" ? 'selected="true"' : '').' value="FX">France, Metropolitan</option>
					<option '.($_POST['register']['director_country']=="GF" ? 'selected="true"' : '').' value="GF">French Guiana</option>
					<option '.($_POST['register']['director_country']=="PF" ? 'selected="true"' : '').' value="PF">French Polynesia</option>
					<option '.($_POST['register']['director_country']=="TF" ? 'selected="true"' : '').' value="TF">French Southern Territories</option>
					<option '.($_POST['register']['director_country']=="GA" ? 'selected="true"' : '').' value="GA">Gabon</option>
					<option '.($_POST['register']['director_country']=="GM" ? 'selected="true"' : '').' value="GM">Gambia</option>
					<option '.($_POST['register']['director_country']=="GE" ? 'selected="true"' : '').' value="GE">Georgia</option>
					<option '.($_POST['register']['director_country']=="DE" ? 'selected="true"' : '').' value="DE">Germany</option>
					<option '.($_POST['register']['director_country']=="GH" ? 'selected="true"' : '').' value="GH">Ghana</option>
					<option '.($_POST['register']['director_country']=="GI" ? 'selected="true"' : '').' value="GI">Gibraltar</option>
					<option '.($_POST['register']['director_country']=="GR" ? 'selected="true"' : '').' value="GR">Greece</option>
					<option '.($_POST['register']['director_country']=="GL" ? 'selected="true"' : '').' value="GL">Greenland</option>
					<option '.($_POST['register']['director_country']=="GD" ? 'selected="true"' : '').' value="GD">Grenada</option>
					<option '.($_POST['register']['director_country']=="GP" ? 'selected="true"' : '').' value="GP">Guadeloupe</option>
					<option '.($_POST['register']['director_country']=="GU" ? 'selected="true"' : '').' value="GU">Guam</option>
					<option '.($_POST['register']['director_country']=="GT" ? 'selected="true"' : '').' value="GT">Guatemala</option>
					<option '.($_POST['register']['director_country']=="GG" ? 'selected="true"' : '').' value="GG">Guernsey</option>
					<option '.($_POST['register']['director_country']=="GN" ? 'selected="true"' : '').' value="GN">Guinea</option>
					<option '.($_POST['register']['director_country']=="GW" ? 'selected="true"' : '').' value="GW">Guinea-bissau</option>
					<option '.($_POST['register']['director_country']=="GY" ? 'selected="true"' : '').' value="GY">Guyana</option>
					<option '.($_POST['register']['director_country']=="HT" ? 'selected="true"' : '').' value="HT">Haiti</option>
					<option '.($_POST['register']['director_country']=="HM" ? 'selected="true"' : '').' value="HM">Heard Island And Mcdonald Islands</option>
					<option '.($_POST['register']['director_country']=="VA" ? 'selected="true"' : '').' value="VA">Holy See (vatican City State)</option>
					<option '.($_POST['register']['director_country']=="HN" ? 'selected="true"' : '').' value="HN">Honduras</option>
					<option '.($_POST['register']['director_country']=="HK" ? 'selected="true"' : '').' value="HK">Hong Kong</option>
					<option '.($_POST['register']['director_country']=="HU" ? 'selected="true"' : '').' value="HU">Hungary</option>
					<option '.($_POST['register']['director_country']=="IS" ? 'selected="true"' : '').' value="IS">Iceland</option>
					<option '.($_POST['register']['director_country']=="IN" ? 'selected="true"' : '').' value="IN">India</option>
					<option '.($_POST['register']['director_country']=="IR" ? 'selected="true"' : '').' value="IR">Iran, Islamic Republic Of</option>
					<option '.($_POST['register']['director_country']=="IQ" ? 'selected="true"' : '').' value="IQ">Iraq</option>
					<option '.($_POST['register']['director_country']=="IE" ? 'selected="true"' : '').' value="IE">Ireland</option>
					<option '.($_POST['register']['director_country']=="IM" ? 'selected="true"' : '').' value="IM">Isle of man</option>
					<option '.($_POST['register']['director_country']=="IL" ? 'selected="true"' : '').' value="IL">Israel</option>
					<option '.($_POST['register']['director_country']=="IT" ? 'selected="true"' : '').' value="IT">Italy</option>
					<option '.($_POST['register']['director_country']=="JM" ? 'selected="true"' : '').' value="JM">Jamaica</option>
					<option '.($_POST['register']['director_country']=="JP" ? 'selected="true"' : '').' value="JP">Japan</option>
					<option '.($_POST['register']['director_country']=="JE" ? 'selected="true"' : '').' value="JE">Jersey</option>
					<option '.($_POST['register']['director_country']=="JO" ? 'selected="true"' : '').' value="JO">Jordan</option>
					<option '.($_POST['register']['director_country']=="KZ" ? 'selected="true"' : '').' value="KZ">Kazakhstan</option>
					<option '.($_POST['register']['director_country']=="KE" ? 'selected="true"' : '').' value="KE">Kenya</option>
					<option '.($_POST['register']['director_country']=="KI" ? 'selected="true"' : '').' value="KI">Kiribati</option>
					<option '.($_POST['register']['director_country']=="KW" ? 'selected="true"' : '').' value="KW">Kuwait</option>
					<option '.($_POST['register']['director_country']=="KG" ? 'selected="true"' : '').' value="KG">Kyrgyzstan</option>
					<option '.($_POST['register']['director_country']=="LA" ? 'selected="true"' : '').' value="LA">Lao People&rsquo;s Democratic Republic</option>
					<option '.($_POST['register']['director_country']=="LV" ? 'selected="true"' : '').' value="LV">Latvia</option>
					<option '.($_POST['register']['director_country']=="LB" ? 'selected="true"' : '').' value="LB">Lebanon</option>
					<option '.($_POST['register']['director_country']=="LS" ? 'selected="true"' : '').' value="LS">Lesotho</option>
					<option '.($_POST['register']['director_country']=="LR" ? 'selected="true"' : '').' value="LR">Liberia</option>
					<option '.($_POST['register']['director_country']=="LY" ? 'selected="true"' : '').' value="LY">Libyan Arab Jamahiriya</option>
					<option '.($_POST['register']['director_country']=="LI" ? 'selected="true"' : '').' value="LI">Liechtenstein</option>
					<option '.($_POST['register']['director_country']=="LT" ? 'selected="true"' : '').' value="LT">Lithuania</option>
					<option '.($_POST['register']['director_country']=="LU" ? 'selected="true"' : '').' value="LU">Luxembourg</option>
					<option '.($_POST['register']['director_country']=="MO" ? 'selected="true"' : '').' value="MO">Macao</option>
					<option '.($_POST['register']['director_country']=="MK" ? 'selected="true"' : '').' value="MK">Macedonia, The Former Yugoslav Republic Of</option>
					<option '.($_POST['register']['director_country']=="MG" ? 'selected="true"' : '').' value="MG">Madagascar</option>
					<option '.($_POST['register']['director_country']=="MW" ? 'selected="true"' : '').' value="MW">Malawi</option>
					<option '.($_POST['register']['director_country']=="MY" ? 'selected="true"' : '').' value="MY">Malaysia</option>
					<option '.($_POST['register']['director_country']=="MV" ? 'selected="true"' : '').' value="MV">Maldives</option>
					<option '.($_POST['register']['director_country']=="ML" ? 'selected="true"' : '').' value="ML">Mali</option>
					<option '.($_POST['register']['director_country']=="MT" ? 'selected="true"' : '').' value="MT">Malta</option>
					<option '.($_POST['register']['director_country']=="MH" ? 'selected="true"' : '').' value="MH">Marshall Islands</option>
					<option '.($_POST['register']['director_country']=="MQ" ? 'selected="true"' : '').' value="MQ">Martinique</option>
					<option '.($_POST['register']['director_country']=="MR" ? 'selected="true"' : '').' value="MR">Mauritania</option>
					<option '.($_POST['register']['director_country']=="MU" ? 'selected="true"' : '').' value="MU">Mauritius</option>
					<option '.($_POST['register']['director_country']=="YT" ? 'selected="true"' : '').' value="YT">Mayotte</option>
					<option '.($_POST['register']['director_country']=="MX" ? 'selected="true"' : '').' value="MX">Mexico</option>
					<option '.($_POST['register']['director_country']=="FM" ? 'selected="true"' : '').' value="FM">Micronesia, Federated States Of</option>
					<option '.($_POST['register']['director_country']=="MD" ? 'selected="true"' : '').' value="MD">Moldova, Republic Of</option>
					<option '.($_POST['register']['director_country']=="MC" ? 'selected="true"' : '').' value="MC">Monaco</option>
					<option '.($_POST['register']['director_country']=="MN" ? 'selected="true"' : '').' value="MN">Mongolia</option>
					<option '.($_POST['register']['director_country']=="MS" ? 'selected="true"' : '').' value="MS">Montserrat</option>
					<option '.($_POST['register']['director_country']=="MZ" ? 'selected="true"' : '').' value="MZ">Mozambique</option>
					<option '.($_POST['register']['director_country']=="MM" ? 'selected="true"' : '').' value="MM">Myanmar</option>
					<option '.($_POST['register']['director_country']=="NA" ? 'selected="true"' : '').' value="NA">Namibia</option>
					<option '.($_POST['register']['director_country']=="NR" ? 'selected="true"' : '').' value="NR">Nauru</option>
					<option '.($_POST['register']['director_country']=="NP" ? 'selected="true"' : '').' value="NP">Nepal</option>
					<option '.($_POST['register']['director_country']=="NL" ? 'selected="true"' : '').' value="NL">Netherlands</option>
					<option '.($_POST['register']['director_country']=="AN" ? 'selected="true"' : '').' value="AN">Netherlands Antilles</option>
					<option '.($_POST['register']['director_country']=="NC" ? 'selected="true"' : '').' value="NC">New Caledonia</option>
					<option '.($_POST['register']['director_country']=="NZ" ? 'selected="true"' : '').' value="NZ">New Zealand</option>
					<option '.($_POST['register']['director_country']=="NI" ? 'selected="true"' : '').' value="NI">Nicaragua</option>
					<option '.($_POST['register']['director_country']=="NE" ? 'selected="true"' : '').' value="NE">Niger</option>
					<option '.($_POST['register']['director_country']=="NU" ? 'selected="true"' : '').' value="NU">Niue</option>
					<option '.($_POST['register']['director_country']=="NF" ? 'selected="true"' : '').' value="NF">Norfolk Island</option>
					<option '.($_POST['register']['director_country']=="MP" ? 'selected="true"' : '').' value="MP">Northern Mariana Islands</option>
					<option '.($_POST['register']['director_country']=="NO" ? 'selected="true"' : '').' value="NO">Norway</option>
					<option '.($_POST['register']['director_country']=="OM" ? 'selected="true"' : '').' value="OM">Oman</option>
					<option '.($_POST['register']['director_country']=="PK" ? 'selected="true"' : '').' value="PK">Pakistan</option>
					<option '.($_POST['register']['director_country']=="PW" ? 'selected="true"' : '').' value="PW">Palau</option>
					<option '.($_POST['register']['director_country']=="PS" ? 'selected="true"' : '').' value="PS">Palestinian Territory, Occupied</option>
					<option '.($_POST['register']['director_country']=="PA" ? 'selected="true"' : '').' value="PA">Panama</option>
					<option '.($_POST['register']['director_country']=="PG" ? 'selected="true"' : '').' value="PG">Papua New Guinea</option>
					<option '.($_POST['register']['director_country']=="PY" ? 'selected="true"' : '').' value="PY">Paraguay</option>
					<option '.($_POST['register']['director_country']=="PE" ? 'selected="true"' : '').' value="PE">Peru</option>
					<option '.($_POST['register']['director_country']=="PH" ? 'selected="true"' : '').' value="PH">Philippines</option>
					<option '.($_POST['register']['director_country']=="PN" ? 'selected="true"' : '').' value="PN">Pitcairn</option>
					<option '.($_POST['register']['director_country']=="PL" ? 'selected="true"' : '').' value="PL">Poland</option>
					<option '.($_POST['register']['director_country']=="PT" ? 'selected="true"' : '').' value="PT">Portugal</option>
					<option '.($_POST['register']['director_country']=="PR" ? 'selected="true"' : '').' value="PR">Puerto Rico</option>
					<option '.($_POST['register']['director_country']=="QA" ? 'selected="true"' : '').' value="QA">Qatar</option>
					<option '.($_POST['register']['director_country']=="ME" ? 'selected="true"' : '').' value="ME">Republic of Montenegro</option>
					<option '.($_POST['register']['director_country']=="RS" ? 'selected="true"' : '').' value="RS">Republic of Serbia</option>
					<option '.($_POST['register']['director_country']=="RE" ? 'selected="true"' : '').' value="RE">Reunion</option>
					<option '.($_POST['register']['director_country']=="RO" ? 'selected="true"' : '').' value="RO">Romania</option>
					<option '.($_POST['register']['director_country']=="RU" ? 'selected="true"' : '').' value="RU">Russian Federation</option>
					<option '.($_POST['register']['director_country']=="RW" ? 'selected="true"' : '').' value="RW">Rwanda</option>
					<option '.($_POST['register']['director_country']=="SH" ? 'selected="true"' : '').' value="SH">Saint Helena</option>
					<option '.($_POST['register']['director_country']=="KN" ? 'selected="true"' : '').' value="KN">Saint Kitts And Nevis</option>
					<option '.($_POST['register']['director_country']=="LC" ? 'selected="true"' : '').' value="LC">Saint Lucia</option>
					<option '.($_POST['register']['director_country']=="PM" ? 'selected="true"' : '').' value="PM">Saint Pierre And Miquelon</option>
					<option '.($_POST['register']['director_country']=="VC" ? 'selected="true"' : '').' value="VC">Saint Vincent And The Grenadines</option>
					<option '.($_POST['register']['director_country']=="WS" ? 'selected="true"' : '').' value="WS">Samoa</option>
					<option '.($_POST['register']['director_country']=="SM" ? 'selected="true"' : '').' value="SM">San Marino</option>
					<option '.($_POST['register']['director_country']=="ST" ? 'selected="true"' : '').' value="ST">Sao Tome And Principe</option>
					<option '.($_POST['register']['director_country']=="SA" ? 'selected="true"' : '').' value="SA">Saudi Arabia</option>
					<option '.($_POST['register']['director_country']=="SN" ? 'selected="true"' : '').' value="SN">Senegal</option>
					<option '.($_POST['register']['director_country']=="CS" ? 'selected="true"' : '').' value="CS">Seychelles</option>
					<option '.($_POST['register']['director_country']=="SC" ? 'selected="true"' : '').' value="SC">Sierra Leone</option>
					<option '.($_POST['register']['director_country']=="SG" ? 'selected="true"' : '').' value="SG">Singapore</option>
					<option '.($_POST['register']['director_country']=="SK" ? 'selected="true"' : '').' value="SK">Slovakia</option>
					<option '.($_POST['register']['director_country']=="SI" ? 'selected="true"' : '').' value="SI">Slovenia</option>
					<option '.($_POST['register']['director_country']=="SB" ? 'selected="true"' : '').' value="SB">Solomon Islands</option>
					<option '.($_POST['register']['director_country']=="SO" ? 'selected="true"' : '').' value="SO">Somalia</option>
					<option '.($_POST['register']['director_country']=="ZA" ? 'selected="true"' : '').' value="ZA">South Africa</option>
					<option '.($_POST['register']['director_country']=="GS" ? 'selected="true"' : '').' value="GS">South Georgia And The South Sandwich Islands</option>
					<option '.($_POST['register']['director_country']=="ES" ? 'selected="true"' : '').' value="ES">Spain</option>
					<option '.($_POST['register']['director_country']=="LK" ? 'selected="true"' : '').' value="LK">Sri Lanka</option>
					<option '.($_POST['register']['director_country']=="SD" ? 'selected="true"' : '').' value="SD">Sudan</option>
					<option '.($_POST['register']['director_country']=="SR" ? 'selected="true"' : '').' value="SR">Suriname</option>
					<option '.($_POST['register']['director_country']=="SJ" ? 'selected="true"' : '').' value="SJ">Svalbard And Jan Mayen</option>
					<option '.($_POST['register']['director_country']=="SZ" ? 'selected="true"' : '').' value="SZ">Swaziland</option>
					<option '.($_POST['register']['director_country']=="SE" ? 'selected="true"' : '').' value="SE">Sweden</option>
					<option '.($_POST['register']['director_country']=="CH" ? 'selected="true"' : '').' value="CH">Switzerland</option>
					<option '.($_POST['register']['director_country']=="SY" ? 'selected="true"' : '').' value="SY">Syrian Arab Republic</option>
					<option '.($_POST['register']['director_country']=="TW" ? 'selected="true"' : '').' value="TW">Taiwan, Province Of China</option>
					<option '.($_POST['register']['director_country']=="TJ" ? 'selected="true"' : '').' value="TJ">Tajikistan</option>
					<option '.($_POST['register']['director_country']=="TZ" ? 'selected="true"' : '').' value="TZ">Tanzania, United Republic Of</option>
					<option '.($_POST['register']['director_country']=="TH" ? 'selected="true"' : '').' value="TH">Thailand</option>
					<option '.($_POST['register']['director_country']=="TL" ? 'selected="true"' : '').' value="TL">Timor-leste</option>
					<option '.($_POST['register']['director_country']=="TG" ? 'selected="true"' : '').' value="TG">Togo</option>
					<option '.($_POST['register']['director_country']=="TK" ? 'selected="true"' : '').' value="TK">Tokelau</option>
					<option '.($_POST['register']['director_country']=="TO" ? 'selected="true"' : '').' value="TO">Tonga</option>
					<option '.($_POST['register']['director_country']=="TI" ? 'selected="true"' : '').' value="TT">Trinidad And Tobago</option>
					<option '.($_POST['register']['director_country']=="TN" ? 'selected="true"' : '').' value="TN">Tunisia</option>
					<option '.($_POST['register']['director_country']=="TR" ? 'selected="true"' : '').' value="TR">Turkey</option>
					<option '.($_POST['register']['director_country']=="TM" ? 'selected="true"' : '').' value="TM">Turkmenistan</option>
					<option '.($_POST['register']['director_country']=="TC" ? 'selected="true"' : '').' value="TC">Turks And Caicos Islands</option>
					<option '.($_POST['register']['director_country']=="TV" ? 'selected="true"' : '').' value="TV">Tuvalu</option>
					<option '.($_POST['register']['director_country']=="UG" ? 'selected="true"' : '').' value="UG">Uganda</option>
					<option '.($_POST['register']['director_country']=="UA" ? 'selected="true"' : '').' value="UA">Ukraine</option>
					<option '.($_POST['register']['director_country']=="AE" ? 'selected="true"' : '').' value="AE">United Arab Emirates</option>
					<option '.($_POST['register']['director_country']=="GB" ? 'selected="true"' : '').' value="GB">United Kingdom</option>
					<option '.($_POST['register']['director_country']=="US" ? 'selected="true"' : '').' value="US">United States</option>
					<option '.($_POST['register']['director_country']=="UM" ? 'selected="true"' : '').' value="UM">United States Minor Outlying Islands</option>
					<option '.($_POST['register']['director_country']=="UY" ? 'selected="true"' : '').' value="UY">Uruguay</option>
					<option '.($_POST['register']['director_country']=="UZ" ? 'selected="true"' : '').' value="UZ">Uzbekistan</option>
					<option '.($_POST['register']['director_country']=="VU" ? 'selected="true"' : '').' value="VU">Vanuatu</option>
					<option '.($_POST['register']['director_country']=="VE" ? 'selected="true"' : '').' value="VE">Venezuela</option>
					<option '.($_POST['register']['director_country']=="VG" ? 'selected="true"' : '').' value="VG">Virgin Islands, British</option>
					<option '.($_POST['register']['director_country']=="VI" ? 'selected="true"' : '').' value="VI">Virgin Islands, U.s.</option>
					<option '.($_POST['register']['director_country']=="WF" ? 'selected="true"' : '').' value="WF">Wallis And Futuna</option>
					<option '.($_POST['register']['director_country']=="EH" ? 'selected="true"' : '').' value="EH">Western Sahara</option>
					<option '.($_POST['register']['director_country']=="YE" ? 'selected="true"' : '').' value="YE">Yemen</option>
					<option '.($_POST['register']['director_country']=="YU" ? 'selected="true"' : '').' value="YU">Yugoslavia</option>
					<option '.($_POST['register']['director_country']=="ZM" ? 'selected="true"' : '').' value="ZM">Zambia</option>
					<option '.($_POST['register']['director_country']=="ZW" ? 'selected="true"' : '').' value="ZW">Zimbabwe</option>
				</select>
			</div>
			<label for="eu_vat">'.$this->l('EU VAT').'</label>
			<div class="margin-form"><input type="text" size="33" id="eu_vat" name="register[eu_vat]" value="'.$_POST['register']['eu_vat'].'" /></div>
			<!--<label for="monthly_turnover">'.$this->l('Monthly turnover (in Euro)').'</label>
			<div class="margin-form"><input type="text" size="33" id="monthly_turnover" name="register[monthly_turnover]" value="'.$_POST['register']['monthly_turnover'].'" /></div>
			-->
			<label for="postal_address">'.$this->l('Postal address').'</label>
			<div class="margin-form"><input type="text" size="33" id="postal_address" name="register[postal_address]" value="'.$_POST['register']['postal_address'].'" /></div>
			<label for="zipcode">'.$this->l('Zipcode').'</label>
			<div class="margin-form"><input type="text" size="33" id="zipcode" name="register[zipcode]" value="'.$_POST['register']['zipcode'].'" /></div>
			<label for="city">'.$this->l('City').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[city]" value="'.$_POST['register']['city'].'" /></div>			
			<label for="country">'.$this->l('Country').'</label>
			<div class="margin-form">
				<select name="register[country]" id="country">
                    <option '.($_POST['register']['country']=="" ? 'selected="true"' : '').' value=""></option>
                    <option '.($_POST['register']['country']=="AF" ? 'selected="true"' : '').' value="AF">Afghanistan</option>
					<option '.($_POST['register']['country']=="AL" ? 'selected="true"' : '').' value="AL">Albania</option>
					<option '.($_POST['register']['country']=="AS" ? 'selected="true"' : '').' value="AS">American Samoa</option>
					<option '.($_POST['register']['country']=="AD" ? 'selected="true"' : '').' value="AD">Andorra</option>
					<option '.($_POST['register']['country']=="AO" ? 'selected="true"' : '').' value="AO">Angola</option>
					<option '.($_POST['register']['country']=="AI" ? 'selected="true"' : '').' value="AI">Anguilla</option>
					<option '.($_POST['register']['country']=="AQ" ? 'selected="true"' : '').' value="AQ">Antarctica</option>
					<option '.($_POST['register']['country']=="AG" ? 'selected="true"' : '').' value="AG">Antigua And Barbuda</option>
					<option '.($_POST['register']['country']=="AR" ? 'selected="true"' : '').' value="AR">Argentina</option>
					<option '.($_POST['register']['country']=="AM" ? 'selected="true"' : '').' value="AM">Armenia</option>
					<option '.($_POST['register']['country']=="AW" ? 'selected="true"' : '').' value="AW">Aruba</option>
					<option '.($_POST['register']['country']=="AU" ? 'selected="true"' : '').' value="AU">Australia</option>
					<option '.($_POST['register']['country']=="AT" ? 'selected="true"' : '').' value="AT">Austria</option>
					<option '.($_POST['register']['country']=="AZ" ? 'selected="true"' : '').' value="AZ">Azerbaijan</option>
					<option '.($_POST['register']['country']=="BS" ? 'selected="true"' : '').' value="BS">Bahamas</option>
					<option '.($_POST['register']['country']=="BH" ? 'selected="true"' : '').' value="BH">Bahrain</option>
					<option '.($_POST['register']['country']=="BD" ? 'selected="true"' : '').' value="BD">Bangladesh</option>
					<option '.($_POST['register']['country']=="BB" ? 'selected="true"' : '').' value="BB">Barbados</option>
					<option '.($_POST['register']['country']=="BY" ? 'selected="true"' : '').' value="BY">Belarus</option>
					<option '.($_POST['register']['country']=="BE" ? 'selected="true"' : '').' value="BE">Belgium</option>
					<option '.($_POST['register']['country']=="BZ" ? 'selected="true"' : '').' value="BZ">Belize</option>
					<option '.($_POST['register']['country']=="BJ" ? 'selected="true"' : '').' value="BJ">Benin</option>
					<option '.($_POST['register']['country']=="BM" ? 'selected="true"' : '').' value="BM">Bermuda</option>
					<option '.($_POST['register']['country']=="BT" ? 'selected="true"' : '').' value="BT">Bhutan</option>
					<option '.($_POST['register']['country']=="BO" ? 'selected="true"' : '').' value="BO">Bolivia</option>
					<option '.($_POST['register']['country']=="BA" ? 'selected="true"' : '').' value="BA">Bosnia And Herzegovina</option>
					<option '.($_POST['register']['country']=="BW" ? 'selected="true"' : '').' value="BW">Botswana</option>
					<option '.($_POST['register']['country']=="BV" ? 'selected="true"' : '').' value="BV">Bouvet Island</option>
					<option '.($_POST['register']['country']=="BR" ? 'selected="true"' : '').' value="BR">Brazil</option>
					<option '.($_POST['register']['country']=="IO" ? 'selected="true"' : '').' value="IO">British Indian Ocean Territory</option>
					<option '.($_POST['register']['country']=="BN" ? 'selected="true"' : '').' value="BN">Brunei Darussalam</option>
					<option '.($_POST['register']['country']=="BG" ? 'selected="true"' : '').' value="BG">Bulgaria</option>
					<option '.($_POST['register']['country']=="BF" ? 'selected="true"' : '').' value="BF">Burkina Faso</option>
					<option '.($_POST['register']['country']=="BI" ? 'selected="true"' : '').' value="BI">Burundi</option>
					<option '.($_POST['register']['country']=="KF" ? 'selected="true"' : '').' value="KF">Cambodia</option>
					<option '.($_POST['register']['country']=="CM" ? 'selected="true"' : '').' value="CM">Cameroon</option>
					<option '.($_POST['register']['country']=="CA" ? 'selected="true"' : '').' value="CA">Canada</option>
					<option '.($_POST['register']['country']=="CV" ? 'selected="true"' : '').' value="CV">Cape Verde</option>
					<option '.($_POST['register']['country']=="KY" ? 'selected="true"' : '').' value="KY">Cayman Islands</option>
					<option '.($_POST['register']['country']=="CF" ? 'selected="true"' : '').' value="CF">Central African Republic</option>
					<option '.($_POST['register']['country']=="TD" ? 'selected="true"' : '').' value="TD">Chad</option>
					<option '.($_POST['register']['country']=="CL" ? 'selected="true"' : '').' value="CL">Chile</option>
					<option '.($_POST['register']['country']=="CN" ? 'selected="true"' : '').' value="CN">China</option>
					<option '.($_POST['register']['country']=="CX" ? 'selected="true"' : '').' value="CX">Christmas Island</option>
					<option '.($_POST['register']['country']=="CC" ? 'selected="true"' : '').' value="CC">Cocos (keeling) Islands</option>
					<option '.($_POST['register']['country']=="CO" ? 'selected="true"' : '').' value="CO">Colombia</option>
					<option '.($_POST['register']['country']=="KM" ? 'selected="true"' : '').' value="KM">Comoros</option>
					<option '.($_POST['register']['country']=="CG" ? 'selected="true"' : '').' value="CG">Congo</option>
					<option '.($_POST['register']['country']=="CD" ? 'selected="true"' : '').' value="CD">Congo, The Democratic Republic Of The</option>
					<option '.($_POST['register']['country']=="CK" ? 'selected="true"' : '').' value="CK">Cook Islands</option>
					<option '.($_POST['register']['country']=="CR" ? 'selected="true"' : '').' value="CR">Costa Rica</option>
					<option '.($_POST['register']['country']=="CI" ? 'selected="true"' : '').' value="CI">Cote D&rsquo;ivoire</option>
					<option '.($_POST['register']['country']=="HR" ? 'selected="true"' : '').' value="HR">Croatia</option>
					<option '.($_POST['register']['country']=="CU" ? 'selected="true"' : '').' value="CU">Cuba</option>
					<option '.($_POST['register']['country']=="CY" ? 'selected="true"' : '').' value="CY">Cyprus</option>
					<option '.($_POST['register']['country']=="CZ" ? 'selected="true"' : '').' value="CZ">Czech Republic</option>
					<option '.($_POST['register']['country']=="DK" ? 'selected="true"' : '').' value="DK">Denmark</option>
					<option '.($_POST['register']['country']=="DJ" ? 'selected="true"' : '').' value="DJ">Djibouti</option>
					<option '.($_POST['register']['country']=="DM" ? 'selected="true"' : '').' value="DM">Dominica</option>
					<option '.($_POST['register']['country']=="DO" ? 'selected="true"' : '').' value="DO">Dominican Republic</option>
					<option '.($_POST['register']['country']=="EC" ? 'selected="true"' : '').' value="EC">Ecuador</option>
					<option '.($_POST['register']['country']=="EG" ? 'selected="true"' : '').' value="EG">Egypt</option>
					<option '.($_POST['register']['country']=="SV" ? 'selected="true"' : '').' value="SV">El Salvador</option>
					<option '.($_POST['register']['country']=="GQ" ? 'selected="true"' : '').' value="GQ">Equatorial Guinea</option>
					<option '.($_POST['register']['country']=="ER" ? 'selected="true"' : '').' value="ER">Eritrea</option>
					<option '.($_POST['register']['country']=="EE" ? 'selected="true"' : '').' value="EE">Estonia</option>
					<option '.($_POST['register']['country']=="ET" ? 'selected="true"' : '').' value="ET">Ethiopia</option>
					<option '.($_POST['register']['country']=="FK" ? 'selected="true"' : '').' value="FK">Falkland Islands (malvinas)</option>
					<option '.($_POST['register']['country']=="FO" ? 'selected="true"' : '').' value="FO">Faroe Islands</option>
					<option '.($_POST['register']['country']=="FJ" ? 'selected="true"' : '').' value="FJ">Fiji</option>
					<option '.($_POST['register']['country']=="FI" ? 'selected="true"' : '').' value="FI">Finland</option>
					<option '.($_POST['register']['country']=="FR" ? 'selected="true"' : '').' value="FR">France</option>
					<option '.($_POST['register']['country']=="FX" ? 'selected="true"' : '').' value="FX">France, Metropolitan</option>
					<option '.($_POST['register']['country']=="GF" ? 'selected="true"' : '').' value="GF">French Guiana</option>
					<option '.($_POST['register']['country']=="PF" ? 'selected="true"' : '').' value="PF">French Polynesia</option>
					<option '.($_POST['register']['country']=="TF" ? 'selected="true"' : '').' value="TF">French Southern Territories</option>
					<option '.($_POST['register']['country']=="GA" ? 'selected="true"' : '').' value="GA">Gabon</option>
					<option '.($_POST['register']['country']=="GM" ? 'selected="true"' : '').' value="GM">Gambia</option>
					<option '.($_POST['register']['country']=="GE" ? 'selected="true"' : '').' value="GE">Georgia</option>
					<option '.($_POST['register']['country']=="DE" ? 'selected="true"' : '').' value="DE">Germany</option>
					<option '.($_POST['register']['country']=="GH" ? 'selected="true"' : '').' value="GH">Ghana</option>
					<option '.($_POST['register']['country']=="GI" ? 'selected="true"' : '').' value="GI">Gibraltar</option>
					<option '.($_POST['register']['country']=="GR" ? 'selected="true"' : '').' value="GR">Greece</option>
					<option '.($_POST['register']['country']=="GL" ? 'selected="true"' : '').' value="GL">Greenland</option>
					<option '.($_POST['register']['country']=="GD" ? 'selected="true"' : '').' value="GD">Grenada</option>
					<option '.($_POST['register']['country']=="GP" ? 'selected="true"' : '').' value="GP">Guadeloupe</option>
					<option '.($_POST['register']['country']=="GU" ? 'selected="true"' : '').' value="GU">Guam</option>
					<option '.($_POST['register']['country']=="GT" ? 'selected="true"' : '').' value="GT">Guatemala</option>
					<option '.($_POST['register']['country']=="GG" ? 'selected="true"' : '').' value="GG">Guernsey</option>
					<option '.($_POST['register']['country']=="GN" ? 'selected="true"' : '').' value="GN">Guinea</option>
					<option '.($_POST['register']['country']=="GW" ? 'selected="true"' : '').' value="GW">Guinea-bissau</option>
					<option '.($_POST['register']['country']=="GY" ? 'selected="true"' : '').' value="GY">Guyana</option>
					<option '.($_POST['register']['country']=="HT" ? 'selected="true"' : '').' value="HT">Haiti</option>
					<option '.($_POST['register']['country']=="HM" ? 'selected="true"' : '').' value="HM">Heard Island And Mcdonald Islands</option>
					<option '.($_POST['register']['country']=="VA" ? 'selected="true"' : '').' value="VA">Holy See (vatican City State)</option>
					<option '.($_POST['register']['country']=="HN" ? 'selected="true"' : '').' value="HN">Honduras</option>
					<option '.($_POST['register']['country']=="HK" ? 'selected="true"' : '').' value="HK">Hong Kong</option>
					<option '.($_POST['register']['country']=="HU" ? 'selected="true"' : '').' value="HU">Hungary</option>
					<option '.($_POST['register']['country']=="IS" ? 'selected="true"' : '').' value="IS">Iceland</option>
					<option '.($_POST['register']['country']=="IN" ? 'selected="true"' : '').' value="IN">India</option>
					<option '.($_POST['register']['country']=="IR" ? 'selected="true"' : '').' value="IR">Iran, Islamic Republic Of</option>
					<option '.($_POST['register']['country']=="IQ" ? 'selected="true"' : '').' value="IQ">Iraq</option>
					<option '.($_POST['register']['country']=="IE" ? 'selected="true"' : '').' value="IE">Ireland</option>
					<option '.($_POST['register']['country']=="IM" ? 'selected="true"' : '').' value="IM">Isle of man</option>
					<option '.($_POST['register']['country']=="IL" ? 'selected="true"' : '').' value="IL">Israel</option>
					<option '.($_POST['register']['country']=="IT" ? 'selected="true"' : '').' value="IT">Italy</option>
					<option '.($_POST['register']['country']=="JM" ? 'selected="true"' : '').' value="JM">Jamaica</option>
					<option '.($_POST['register']['country']=="JP" ? 'selected="true"' : '').' value="JP">Japan</option>
					<option '.($_POST['register']['country']=="JE" ? 'selected="true"' : '').' value="JE">Jersey</option>
					<option '.($_POST['register']['country']=="JO" ? 'selected="true"' : '').' value="JO">Jordan</option>
					<option '.($_POST['register']['country']=="KZ" ? 'selected="true"' : '').' value="KZ">Kazakhstan</option>
					<option '.($_POST['register']['country']=="KE" ? 'selected="true"' : '').' value="KE">Kenya</option>
					<option '.($_POST['register']['country']=="KI" ? 'selected="true"' : '').' value="KI">Kiribati</option>
					<option '.($_POST['register']['country']=="KW" ? 'selected="true"' : '').' value="KW">Kuwait</option>
					<option '.($_POST['register']['country']=="KG" ? 'selected="true"' : '').' value="KG">Kyrgyzstan</option>
					<option '.($_POST['register']['country']=="LA" ? 'selected="true"' : '').' value="LA">Lao People&rsquo;s Democratic Republic</option>
					<option '.($_POST['register']['country']=="LV" ? 'selected="true"' : '').' value="LV">Latvia</option>
					<option '.($_POST['register']['country']=="LB" ? 'selected="true"' : '').' value="LB">Lebanon</option>
					<option '.($_POST['register']['country']=="LS" ? 'selected="true"' : '').' value="LS">Lesotho</option>
					<option '.($_POST['register']['country']=="LR" ? 'selected="true"' : '').' value="LR">Liberia</option>
					<option '.($_POST['register']['country']=="LY" ? 'selected="true"' : '').' value="LY">Libyan Arab Jamahiriya</option>
					<option '.($_POST['register']['country']=="LI" ? 'selected="true"' : '').' value="LI">Liechtenstein</option>
					<option '.($_POST['register']['country']=="LT" ? 'selected="true"' : '').' value="LT">Lithuania</option>
					<option '.($_POST['register']['country']=="LU" ? 'selected="true"' : '').' value="LU">Luxembourg</option>
					<option '.($_POST['register']['country']=="MO" ? 'selected="true"' : '').' value="MO">Macao</option>
					<option '.($_POST['register']['country']=="MK" ? 'selected="true"' : '').' value="MK">Macedonia, The Former Yugoslav Republic Of</option>
					<option '.($_POST['register']['country']=="MG" ? 'selected="true"' : '').' value="MG">Madagascar</option>
					<option '.($_POST['register']['country']=="MW" ? 'selected="true"' : '').' value="MW">Malawi</option>
					<option '.($_POST['register']['country']=="MY" ? 'selected="true"' : '').' value="MY">Malaysia</option>
					<option '.($_POST['register']['country']=="MV" ? 'selected="true"' : '').' value="MV">Maldives</option>
					<option '.($_POST['register']['country']=="ML" ? 'selected="true"' : '').' value="ML">Mali</option>
					<option '.($_POST['register']['country']=="MT" ? 'selected="true"' : '').' value="MT">Malta</option>
					<option '.($_POST['register']['country']=="MH" ? 'selected="true"' : '').' value="MH">Marshall Islands</option>
					<option '.($_POST['register']['country']=="MQ" ? 'selected="true"' : '').' value="MQ">Martinique</option>
					<option '.($_POST['register']['country']=="MR" ? 'selected="true"' : '').' value="MR">Mauritania</option>
					<option '.($_POST['register']['country']=="MU" ? 'selected="true"' : '').' value="MU">Mauritius</option>
					<option '.($_POST['register']['country']=="YT" ? 'selected="true"' : '').' value="YT">Mayotte</option>
					<option '.($_POST['register']['country']=="MX" ? 'selected="true"' : '').' value="MX">Mexico</option>
					<option '.($_POST['register']['country']=="FM" ? 'selected="true"' : '').' value="FM">Micronesia, Federated States Of</option>
					<option '.($_POST['register']['country']=="MD" ? 'selected="true"' : '').' value="MD">Moldova, Republic Of</option>
					<option '.($_POST['register']['country']=="MC" ? 'selected="true"' : '').' value="MC">Monaco</option>
					<option '.($_POST['register']['country']=="MN" ? 'selected="true"' : '').' value="MN">Mongolia</option>
					<option '.($_POST['register']['country']=="MS" ? 'selected="true"' : '').' value="MS">Montserrat</option>
					<option '.($_POST['register']['country']=="MZ" ? 'selected="true"' : '').' value="MZ">Mozambique</option>
					<option '.($_POST['register']['country']=="MM" ? 'selected="true"' : '').' value="MM">Myanmar</option>
					<option '.($_POST['register']['country']=="NA" ? 'selected="true"' : '').' value="NA">Namibia</option>
					<option '.($_POST['register']['country']=="NR" ? 'selected="true"' : '').' value="NR">Nauru</option>
					<option '.($_POST['register']['country']=="NP" ? 'selected="true"' : '').' value="NP">Nepal</option>
					<option '.($_POST['register']['country']=="NL" ? 'selected="true"' : '').' value="NL">Netherlands</option>
					<option '.($_POST['register']['country']=="AN" ? 'selected="true"' : '').' value="AN">Netherlands Antilles</option>
					<option '.($_POST['register']['country']=="NC" ? 'selected="true"' : '').' value="NC">New Caledonia</option>
					<option '.($_POST['register']['country']=="NZ" ? 'selected="true"' : '').' value="NZ">New Zealand</option>
					<option '.($_POST['register']['country']=="NI" ? 'selected="true"' : '').' value="NI">Nicaragua</option>
					<option '.($_POST['register']['country']=="NE" ? 'selected="true"' : '').' value="NE">Niger</option>
					<option '.($_POST['register']['country']=="NU" ? 'selected="true"' : '').' value="NU">Niue</option>
					<option '.($_POST['register']['country']=="NF" ? 'selected="true"' : '').' value="NF">Norfolk Island</option>
					<option '.($_POST['register']['country']=="MP" ? 'selected="true"' : '').' value="MP">Northern Mariana Islands</option>
					<option '.($_POST['register']['country']=="NO" ? 'selected="true"' : '').' value="NO">Norway</option>
					<option '.($_POST['register']['country']=="OM" ? 'selected="true"' : '').' value="OM">Oman</option>
					<option '.($_POST['register']['country']=="PK" ? 'selected="true"' : '').' value="PK">Pakistan</option>
					<option '.($_POST['register']['country']=="PW" ? 'selected="true"' : '').' value="PW">Palau</option>
					<option '.($_POST['register']['country']=="PS" ? 'selected="true"' : '').' value="PS">Palestinian Territory, Occupied</option>
					<option '.($_POST['register']['country']=="PA" ? 'selected="true"' : '').' value="PA">Panama</option>
					<option '.($_POST['register']['country']=="PG" ? 'selected="true"' : '').' value="PG">Papua New Guinea</option>
					<option '.($_POST['register']['country']=="PY" ? 'selected="true"' : '').' value="PY">Paraguay</option>
					<option '.($_POST['register']['country']=="PE" ? 'selected="true"' : '').' value="PE">Peru</option>
					<option '.($_POST['register']['country']=="PH" ? 'selected="true"' : '').' value="PH">Philippines</option>
					<option '.($_POST['register']['country']=="PN" ? 'selected="true"' : '').' value="PN">Pitcairn</option>
					<option '.($_POST['register']['country']=="PL" ? 'selected="true"' : '').' value="PL">Poland</option>
					<option '.($_POST['register']['country']=="PT" ? 'selected="true"' : '').' value="PT">Portugal</option>
					<option '.($_POST['register']['country']=="PR" ? 'selected="true"' : '').' value="PR">Puerto Rico</option>
					<option '.($_POST['register']['country']=="QA" ? 'selected="true"' : '').' value="QA">Qatar</option>
					<option '.($_POST['register']['country']=="ME" ? 'selected="true"' : '').' value="ME">Republic of Montenegro</option>
					<option '.($_POST['register']['country']=="RS" ? 'selected="true"' : '').' value="RS">Republic of Serbia</option>
					<option '.($_POST['register']['country']=="RE" ? 'selected="true"' : '').' value="RE">Reunion</option>
					<option '.($_POST['register']['country']=="RO" ? 'selected="true"' : '').' value="RO">Romania</option>
					<option '.($_POST['register']['country']=="RU" ? 'selected="true"' : '').' value="RU">Russian Federation</option>
					<option '.($_POST['register']['country']=="RW" ? 'selected="true"' : '').' value="RW">Rwanda</option>
					<option '.($_POST['register']['country']=="SH" ? 'selected="true"' : '').' value="SH">Saint Helena</option>
					<option '.($_POST['register']['country']=="KN" ? 'selected="true"' : '').' value="KN">Saint Kitts And Nevis</option>
					<option '.($_POST['register']['country']=="LC" ? 'selected="true"' : '').' value="LC">Saint Lucia</option>
					<option '.($_POST['register']['country']=="PM" ? 'selected="true"' : '').' value="PM">Saint Pierre And Miquelon</option>
					<option '.($_POST['register']['country']=="VC" ? 'selected="true"' : '').' value="VC">Saint Vincent And The Grenadines</option>
					<option '.($_POST['register']['country']=="WS" ? 'selected="true"' : '').' value="WS">Samoa</option>
					<option '.($_POST['register']['country']=="SM" ? 'selected="true"' : '').' value="SM">San Marino</option>
					<option '.($_POST['register']['country']=="ST" ? 'selected="true"' : '').' value="ST">Sao Tome And Principe</option>
					<option '.($_POST['register']['country']=="SA" ? 'selected="true"' : '').' value="SA">Saudi Arabia</option>
					<option '.($_POST['register']['country']=="SN" ? 'selected="true"' : '').' value="SN">Senegal</option>
					<option '.($_POST['register']['country']=="CS" ? 'selected="true"' : '').' value="CS">Seychelles</option>
					<option '.($_POST['register']['country']=="SC" ? 'selected="true"' : '').' value="SC">Sierra Leone</option>
					<option '.($_POST['register']['country']=="SG" ? 'selected="true"' : '').' value="SG">Singapore</option>
					<option '.($_POST['register']['country']=="SK" ? 'selected="true"' : '').' value="SK">Slovakia</option>
					<option '.($_POST['register']['country']=="SI" ? 'selected="true"' : '').' value="SI">Slovenia</option>
					<option '.($_POST['register']['country']=="SB" ? 'selected="true"' : '').' value="SB">Solomon Islands</option>
					<option '.($_POST['register']['country']=="SO" ? 'selected="true"' : '').' value="SO">Somalia</option>
					<option '.($_POST['register']['country']=="ZA" ? 'selected="true"' : '').' value="ZA">South Africa</option>
					<option '.($_POST['register']['country']=="GS" ? 'selected="true"' : '').' value="GS">South Georgia And The South Sandwich Islands</option>
					<option '.($_POST['register']['country']=="ES" ? 'selected="true"' : '').' value="ES">Spain</option>
					<option '.($_POST['register']['country']=="LK" ? 'selected="true"' : '').' value="LK">Sri Lanka</option>
					<option '.($_POST['register']['country']=="SD" ? 'selected="true"' : '').' value="SD">Sudan</option>
					<option '.($_POST['register']['country']=="SR" ? 'selected="true"' : '').' value="SR">Suriname</option>
					<option '.($_POST['register']['country']=="SJ" ? 'selected="true"' : '').' value="SJ">Svalbard And Jan Mayen</option>
					<option '.($_POST['register']['country']=="SZ" ? 'selected="true"' : '').' value="SZ">Swaziland</option>
					<option '.($_POST['register']['country']=="SE" ? 'selected="true"' : '').' value="SE">Sweden</option>
					<option '.($_POST['register']['country']=="CH" ? 'selected="true"' : '').' value="CH">Switzerland</option>
					<option '.($_POST['register']['country']=="SY" ? 'selected="true"' : '').' value="SY">Syrian Arab Republic</option>
					<option '.($_POST['register']['country']=="TW" ? 'selected="true"' : '').' value="TW">Taiwan, Province Of China</option>
					<option '.($_POST['register']['country']=="TJ" ? 'selected="true"' : '').' value="TJ">Tajikistan</option>
					<option '.($_POST['register']['country']=="TZ" ? 'selected="true"' : '').' value="TZ">Tanzania, United Republic Of</option>
					<option '.($_POST['register']['country']=="TH" ? 'selected="true"' : '').' value="TH">Thailand</option>
					<option '.($_POST['register']['country']=="TL" ? 'selected="true"' : '').' value="TL">Timor-leste</option>
					<option '.($_POST['register']['country']=="TG" ? 'selected="true"' : '').' value="TG">Togo</option>
					<option '.($_POST['register']['country']=="TK" ? 'selected="true"' : '').' value="TK">Tokelau</option>
					<option '.($_POST['register']['country']=="TO" ? 'selected="true"' : '').' value="TO">Tonga</option>
					<option '.($_POST['register']['country']=="TI" ? 'selected="true"' : '').' value="TT">Trinidad And Tobago</option>
					<option '.($_POST['register']['country']=="TN" ? 'selected="true"' : '').' value="TN">Tunisia</option>
					<option '.($_POST['register']['country']=="TR" ? 'selected="true"' : '').' value="TR">Turkey</option>
					<option '.($_POST['register']['country']=="TM" ? 'selected="true"' : '').' value="TM">Turkmenistan</option>
					<option '.($_POST['register']['country']=="TC" ? 'selected="true"' : '').' value="TC">Turks And Caicos Islands</option>
					<option '.($_POST['register']['country']=="TV" ? 'selected="true"' : '').' value="TV">Tuvalu</option>
					<option '.($_POST['register']['country']=="UG" ? 'selected="true"' : '').' value="UG">Uganda</option>
					<option '.($_POST['register']['country']=="UA" ? 'selected="true"' : '').' value="UA">Ukraine</option>
					<option '.($_POST['register']['country']=="AE" ? 'selected="true"' : '').' value="AE">United Arab Emirates</option>
					<option '.($_POST['register']['country']=="GB" ? 'selected="true"' : '').' value="GB">United Kingdom</option>
					<option '.($_POST['register']['country']=="US" ? 'selected="true"' : '').' value="US">United States</option>
					<option '.($_POST['register']['country']=="UM" ? 'selected="true"' : '').' value="UM">United States Minor Outlying Islands</option>
					<option '.($_POST['register']['country']=="UY" ? 'selected="true"' : '').' value="UY">Uruguay</option>
					<option '.($_POST['register']['country']=="UZ" ? 'selected="true"' : '').' value="UZ">Uzbekistan</option>
					<option '.($_POST['register']['country']=="VU" ? 'selected="true"' : '').' value="VU">Vanuatu</option>
					<option '.($_POST['register']['country']=="VE" ? 'selected="true"' : '').' value="VE">Venezuela</option>
					<option '.($_POST['register']['country']=="VG" ? 'selected="true"' : '').' value="VG">Virgin Islands, British</option>
					<option '.($_POST['register']['country']=="VI" ? 'selected="true"' : '').' value="VI">Virgin Islands, U.s.</option>
					<option '.($_POST['register']['country']=="WF" ? 'selected="true"' : '').' value="WF">Wallis And Futuna</option>
					<option '.($_POST['register']['country']=="EH" ? 'selected="true"' : '').' value="EH">Western Sahara</option>
					<option '.($_POST['register']['country']=="YE" ? 'selected="true"' : '').' value="YE">Yemen</option>
					<option '.($_POST['register']['country']=="YU" ? 'selected="true"' : '').' value="YU">Yugoslavia</option>
					<option '.($_POST['register']['country']=="ZM" ? 'selected="true"' : '').' value="ZM">Zambia</option>
					<option '.($_POST['register']['country']=="ZW" ? 'selected="true"' : '').' value="ZW">Zimbabwe</option>
				</select>
			</div>
			<label for="phone_number">'.$this->l('Phone number').'</label>
			<div class="margin-form"><input type="text" size="33" id="phone_number" name="register[phone_number]" value="'.$_POST['register']['phone_number'].'" /></div>
			<label for="email">'.$this->l('Email').'</label>
			<div class="margin-form"><input type="text" size="33" id="email" name="register[email]" value="'.$_POST['register']['email'].'" /></div>
			<!--
			<label for="main_website_url">'.$this->l('Main website URL').'</label>
			<div class="margin-form"><input type="text" size="33" id="main_website_url" name="register[main_website_url]" value="'.$_POST['register']['main_website_url'].'" /></div>
			-->
			<label for="business_description">'.$this->l('Business description').'</label>
			<div class="margin-form"><input type="text" size="33" id="business_description" name="register[business_description]" value="'.$_POST['register']['business_description'].'" /></div>
			<label for="username">'.$this->l('Username').'</label>
			<div class="margin-form"><input type="text" size="33" id="username" name="register[username]" value="'.$_POST['register']['username'].'" /></div>
			<!--
			<label>'.$this->l('Site').'</label>
			<div class="margin-form">&nbsp;</div>
			<label for="city">'.$this->l('Site URL').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[site_url]" value="'.$_POST['register']['site_url'].'" /></div>
			<label for="city">'.$this->l('Confirmation URL').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[site_confirmation_url]" value="'.$_POST['register']['site_confirmation_url'].'" /></div>
			<label for="city">'.$this->l('Confirmation URL username').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[confirm_user]" value="'.$_POST['register']['confirm_user'].'" /></div>
			<label for="city">'.$this->l('Confirmation URL password').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[confirm_password]" value="'.$_POST['register']['confirm_password'].'" /></div>
			<label for="city">'.$this->l('Alert URL').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[alert_url]" value="'.$_POST['register']['alert_url'].'" /></div>
			<label for="city">'.$this->l('After payment URL').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[after_payment_url]" value="'.$_POST['register']['after_payment_url'].'" /></div>
			<label for="city">'.$this->l('Cancelled payment URL').'</label>
			<div class="margin-form"><input type="text" size="33" id="city" name="register[cancelled_payment_url]" value="'.$_POST['register']['cancelled_payment_url'].'" /></div>	
			-->
			<br /><center><input type="submit" name="registerPSC" value="'.$this->l('Registration in Paysite-cash').'" class="button" /></center>
			</form>
			</div>
			<div name="pscnewaccount" '.($api_key ? '' : 'style="display:none;"').'>
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<hr>
			<label>'.$this->l('Merchant authentication').'</label>
			<div class="margin-form">&nbsp;</div>
			<label>'.$this->l('Have already Paysite-cash merchant API key?').'</label>
			<div class="margin-form">
				<input type="radio" onclick="pscnewapikey()" name="pscapikey" value="1" '.(!$api_key ? 'checked="checked"' : '').' /> '.$this->l('No').'
				<input type="radio" onclick="pscnewapikey()" name="pscapikey" value="0" '.($api_key ? 'checked="checked"' : '').' /> '.$this->l('Yes').'
			</div><br />
			<script type="text/javascript">
			function pscnewapikey() {
				if (document.getElementsByName("pscapikey")[0].checked==true) {
					document.getElementsByName("pscnewapikey")[0].style.display=""					
				} else {
					document.getElementsByName("pscnewapikey")[0].style.display="none"					
				}
			}
			</script>
			<label for="service_api_key">'.$this->l('Merchant API key').'</label><div class="margin-form"><input type="text" size="33" id="service_api_key" name="service[api_key]" value="'.htmlentities($api_key, ENT_COMPAT, 'UTF-8').'" /></div>
			
			<div name="pscnewapikey" '.($api_key ? 'style="display:none;"' : '').'>
			<div class="margin-form">&nbsp;</div>
			<label for="service_username">'.$this->l('Username').'</label><div class="margin-form"><input type="text" size="33" id="service_username" name="service[username]" id="service_username" value="" /></div>
			<label for="service_password">'.$this->l('Password').'</label><div class="margin-form"><input type="password" size="33" id="service_password" name="service[password]" id="service_password" value="" /></div>
			<br /><center><input type="submit" name="getnewAPIkey" value="'.$this->l('Get API key').'" class="button" /></center>
			</div>
			</form>
			<hr>
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<input type="hidden" id="pscaccount2" name="pscaccount2" value="'.($pscaccount || $api_key ? '1' : '0').'" />
			<input type="hidden" id="service_api_key" name="service[api_key]" value="'.htmlentities($api_key, ENT_COMPAT, 'UTF-8').'" />
			<label>'.$this->l('Site details').'</label>
			<div class="margin-form">&nbsp;</div>
			<label for="site_create_name">'.$this->l('Site name').'</label>
			<div class="margin-form"><input type="text" size="33" id="site_create_name" name="site_create[name]" value="'.$_POST['site_create']['name'].'" /></div>
			<label for="site_create_url">'.$this->l('Site URL').'</label>
			<div class="margin-form"><input type="text" size="70" id="site_create_url" name="site_create[url]" value="'.$_POST['site_create']['url'].'" /></div>
			<label for="site_customer_contact_email">'.$this->l('Customer email contact').'</label>
			<div class="margin-form"><input type="text" size="33" id="site_customer_contact_email" name="site_create[customer_contact_email]" value="'.$_POST['site_create']['customer_contact_email'].'" /></div>
			<label for="site_create_selling_goods">'.$this->l('Site is selling goods').'</label>
            <div class="margin-form"><input type="checkbox" name="site_create[is_selling_goods]" id="site_create_selling_goods" '.(isset($_POST['site_create']['is_selling_goods']) ? 'checked="checked"' : '').'></div>
			<hr>
            <label>'.$this->l('URLs and BackOffice').'</label>
			<div class="margin-form">&nbsp;</div>
			<label for="site_create_referrer_url">'.$this->l('Referrer URL').'</label>
			<div class="margin-form"><input type="text" size="70" id="site_create_referrer_url" name="site_create[referrer_url]" value="'.$_POST['site_create']['referrer_url'].'" /></div>
			<label for="site_create_url_ok">'.$this->l('After payment URL').'</label>
			<div class="margin-form"><input type="text" size="70" id="site_create_url_ok" name="site_create[after_payment_url]" value="'.$_POST['site_create']['after_payment_url'].'" /></div>
			<label for="site_create_url_nok">'.$this->l('Payment cancelled URL').'</label>
			<div class="margin-form"><input type="text" size="70" id="site_create_url_nok" name="site_create[cancelled_payment_url]" value="'.$_POST['site_create']['cancelled_payment_url'].'" /></div>
			<label for="site_create_confirm_url">'.$this->l('BackOffice confirmation url').'</label>
			<div class="margin-form"><input type="text" size="70" id="site_create_confirm_url" name="site_create[backoffice_url]" value="'.$_POST['site_create']['backoffice_url'].'" /></div>
			<label for="site_create_confirm_user">'.$this->l('BackOffice Username (if required)').'</label>
			<div class="margin-form"><input type="text" size="30" id="site_create_confirm_user" name="site_create[backoffice_url_username]" value="'.$_POST['site_create']['backoffice_url_username'].'" /></div></br>
			<label for="site_create_confirm_pass">'.$this->l('BackOffice Password (if required)').'</label></br>
			<div class="margin-form"><input type="text" size="30" id="site_create_confirm_pass" name="site_create[backoffice_url_password]" value="'.$_POST['site_create']['backoffice_url_password'].'" /></div>
			<hr>
			<label>'.$this->l('Anti-fraud settings').'</label>
			<div class="margin-form">&nbsp;</div>
			<label for="site_create_multiple_payments_alert_limit">'.$this->l('Multiple payments alert limit').'</label>
			<div class="margin-form"><input type="text" size="15" id="site_create_multiple_payments_alert_limit" name="site_create[multiple_payments_alert_limit]" value="'.$_POST['site_create']['multiple_payments_alert_limit'].'" /></div>
			<label for="site_create_accept_abroad_payments">'.$this->l('Accept abroad payments').'</label>
			<div class="margin-form"><input type="checkbox" id="site_create_accept_abroad_payments" name="site_create[accept_payment_from_abroad]" '.(isset($_POST['site_create']['accept_payment_from_abroad']) ? 'checked="checked"' : '').' /></div>
			<label for="site_create_accept_free_emails">'.$this->l('Accept free emails').'</label>
			<div class="margin-form"><input type="checkbox" id="site_create_accept_free_emails" name="site_create[accept_free_emails]" '.(isset($_POST['site_create']['accept_free_emails']) ? 'checked="checked"' : '').' /></div>
			<label for="site_create_accept_multiple_subscriptions">'.$this->l('Accept multiple subscriptions').'</label>
			<div class="margin-form"><input type="checkbox" id="site_create_accept_multiple_subscriptions" name="site_create[accept_multiple_subscriptions]" '.(isset($_POST['site_create']['accept_multiple_subscriptions']) ? 'checked="checked"' : '').' /></div>
			<label for="site_create_validate_by_sms">'.$this->l('Validate by SMS').'</label>
			<div class="margin-form"><input type="checkbox" id="site_create_validate_by_sms" name="site_create[validate_by_sms]]" '.(isset($_POST['site_create']['validate_by_sms']) ? 'checked="checked"' : '').' /></div>
			<label for="site_create_validate_by_phone">'.$this->l('Validate by phone').'</label>
			<div class="margin-form"><input type="checkbox" id="site_create_validate_by_phone" name="site_create[validate_by_phone]" '.(isset($_POST['site_create']['validate_by_phone']) ? 'checked="checked"' : '').' /></div>
			<label for="site_create_revalidate_after_months">'.$this->l('Revalidate cardholder after').'</label>
			<div class="margin-form">
			    <select width=2 name="site_create[revalidate_after_months]" id="site_create_revalidate_after_months">
                    <option '.($_POST['site_create']['revalidate_after_months']==1 ? 'selected="true"' : '').' value="1">1</option>
                    <option '.($_POST['site_create']['revalidate_after_months']==2 ? 'selected="true"' : '').' value="2">2</option>
                    <option '.($_POST['site_create']['revalidate_after_months']==3 ? 'selected="true"' : '').' value="3">3</option>
                    <option '.($_POST['site_create']['revalidate_after_months']==4 ? 'selected="true"' : '').' value="4">4</option>
                    <option '.($_POST['site_create']['revalidate_after_months']==5 ? 'selected="true"' : '').' value="5">5</option>
                    <option '.($_POST['site_create']['revalidate_after_months']==6 ? 'selected="true"' : '').' value="6">6</option>
                </select> '.$this->l('months').'
			</div>
			<label for="site_create_revalidate_after_amount">'.$this->l('Revalidate cardholder after').'</label>
			<div class="margin-form"><input type="text" size="20 id="site_create_revalidate_after_amount" name="site_create[revalidate_after_amount]" value="'.$_POST['site_create']['revalidate_after_amount'].'" /> &euro;</div>
			<label for="site_create_request_activation">'.$this->l('Request activation?').'</label>
			<div class="margin-form"><input type="checkbox" id="site_create_request_activation" name="site_create[request_activation]" '.(isset($_POST['site_create']['request_activation']) ? 'checked="checked"' : '').' /></div>
			<br /><center><input type="submit" name="submitnewsitePSC" value="'.$this->l('Create new site').'" class="button" /></center>
			</form>
			</div>
			
		</fieldset><br />
		'.(!$pscaccount && !$api_key  ? '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
		<fieldset style="width:95%;">
			<legend><img src="../img/admin/contact.gif" />'.$this->l('Settings').'</legend>
			<label>'.$this->l('Paysite-cash Site ID').'</label>
			<div class="margin-form"><input type="text" size="33" name="siteid" value="'.htmlentities($siteid, ENT_COMPAT, 'UTF-8').'" /></div>
			<label>'.$this->l('Test mode').'</label>
			<div class="margin-form">
				<input type="radio" name="testmode" value="1" '.($testmode ? 'checked="checked"' : '').' /> '.$this->l('Yes').'
				<input type="radio" name="testmode" value="0" '.(!$testmode ? 'checked="checked"' : '').' /> '.$this->l('No').'
			</div>
			<label>'.$this->l('Debug mode').'</label>
			<div class="margin-form">
				<input type="radio" name="debugmode" value="1" '.($debugmode ? 'checked="checked"' : '').' /> '.$this->l('Yes').'
				<input type="radio" name="debugmode" value="0" '.(!$debugmode ? 'checked="checked"' : '').' /> '.$this->l('No').'
			</div>
			<label>'.$this->l('Gateway').'</label>
			<div class="margin-form">
		                <select name="gateway">
				<option value="paysite" '.($gateway=='paysite' ? 'selected="true"' : '').'>Paysite-Cash</option>
				<option value="easypay" '.($gateway=='easypay' ? 'selected="true"' : '').'>Easy-Pay</option>
				</select>
			</div>
			<br /><center><input type="submit" name="submitPSC" value="'.$this->l('Update settings').'" class="button" /></center>
		</fieldset>
		</form><br /><br />' : '').'
		<fieldset style="width:95%;">
			<legend><img src="../img/admin/warning.gif" />'.$this->l('Credit Card Test Info').'</legend>
			<ul style="font-size: 0.9em; font-style: italic; margin-bottom: 0;">
			<li>'.$this->l('Card Type').': Visa</li>
			<li><dl><dt>'.$this->l('Card Number').':</dt>
				<dd>-  1111111111111111 -> '.$this->l('accepted').'</dd>
				<dd>-  2222222222222222 -> '.$this->l('declined').'</dd></li><br/><br/>
			<b style="color: red;">'.$this->l('All PrestaShop currencies must be also configured</b> inside Localisation  > Devises').'<br />
		</fieldset>
		
		';
	}

	public function hookPayment($params)
	{
		global $smarty;

		$address = new Address(intval($params['cart']->id_address_invoice));
		$customer = new Customer(intval($params['cart']->id_customer));
		$siteid = Configuration::get('PSC_SITEID');
		$gateway = Configuration::get('PSC_GATEWAY');
		$testmode = Configuration::get('PSC_TESTMODE');
		$debugmode = Configuration::get('PSC_DEBUGMODE');
		$currency = $this->getCurrency();

		if (!intval($siteid))
			return $this->l('Paysite-cash error: (invalid or undefined siteid)');

		if (!Validate::isLoadedObject($address) OR !Validate::isLoadedObject($customer) OR !Validate::isLoadedObject($currency))
			return $this->l('Error: (invalid address or customer)');

		$products = $params['cart']->getProducts();

		foreach ($products as $key => $product)
		{
			$products[$key]['name'] = str_replace('"', '\'', $product['name']);
			if (isset($product['attributes']))
				$products[$key]['attributes'] = str_replace('"', '\'', $product['attributes']);
			$products[$key]['name'] = htmlentities(utf8_decode($product['name']));
			$products[$key]['pscAmount'] = number_format(Tools::convertPrice($product['price_wt'], $currency), 2, '.', '');
		}

		$key=md5("secret_key".(0)."".intval($params['cart']->id));
		$divers = base64_encode("key=".$key."&ref=".intval($params['cart']->id)."&sk=".$customer->secure_key."&id_cart=".intval($params['cart']->id)."&id_module=".intval($this->id));

		$smarty->assign(array(
			'site' 		=> $siteid,
			'montant'	=> number_format(Tools::convertPrice($params['cart']->getOrderTotal(true, 3), $currency), 2, '.', ''),
			'devise'	=> $currency,
			'test'		=> $testmode,
			'debug'		=> $debugmode,
			'ref' 		=> intval($params['cart']->id),
			'divers'	=> $divers,
			'pscUrl' 	=> $this->getPSCUrl(),
			'sitetitle'	=> (($gateway=='easypay')?$this->getL('easypay'):$this->getL('paysite')),
			'logo'		=> $gateway,

/*			'address' => $address,
			'country' => new Country(intval($address->id_country)),
			'customer' => $customer,
			'pscUrl' => $this->getPSCUrl(),
			'shipping' =>  number_format(Tools::convertPrice(($params['cart']->getOrderShippingCost() + $params['cart']->getOrderTotal(true, 6)), $currency), 2, '.', ''),
			'discounts' => $params['cart']->getDiscounts(),
			'products' => $products,
			'total' => number_format(Tools::convertPrice($params['cart']->getOrderTotal(true, 3), $currency), 2, '.', ''),
			'id_cart' => intval($params['cart']->id),
			'goBackUrl' => 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.intval($params['cart']->id).'&id_module='.intval($this->id),
			'returnUrl' => 'http://'.htmlspecialchars($_SERVER['HTTP_HOST'], ENT_COMPAT, 'UTF-8').__PS_BASE_URI__.'modules/psc/validation.php',
			'this_path' => $this->_path
*/
		)
		);

		return $this->display(__FILE__, 'psc.tpl');
    }

	public function getL($key)
	{
		$translations = array(
			'paysite' => $this->l('Paysite-cash (Credit Cards and Alternatives)'),
			'easypay' => $this->l('Credit Card Payment'),
			'montant' => $this->l('Key \'montant\' not specified, can\'t control amount paid.'),
			'payment' => $this->l('Payment: '),
			'cart' => $this->l('Cart not found'),
			'order' => $this->l('Order has already been placed'),
			'transaction' => $this->l('Transaction ID: '),
			'verified' => $this->l('The transaction could not be VERIFIED.'),
			'connect' => $this->l('Problem connecting to the server.'),
			'nomethod' => $this->l('No communications transport available.'),
			'socketmethod' => $this->l('Verification failure (using fsockopen). Returned: '),
			'curlmethod' => $this->l('Verification failure (using cURL). Returned: '),
			'curlmethodfailed' => $this->l('Connection using cURL failed'),
			'access' => $this->l('Restricted access'),
		);
		return $translations[$key];
	}
}

?>