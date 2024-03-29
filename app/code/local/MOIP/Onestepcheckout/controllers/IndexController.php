<?php
require_once 'Mage/Checkout/controllers/OnepageController.php';
class MOIP_Onestepcheckout_IndexController extends Mage_Checkout_OnepageController
{ protected $notshiptype=0;
	public function getCheckout() {
		return Mage::getSingleton('checkout/session');
	}
	public function getQuote() {
		return Mage::getSingleton('checkout/session')->getQuote();
	}
	public function getOnepage() {
		return Mage::getSingleton('checkout/type_onepage');
	}

	protected function _getQuote() {
		return Mage::getSingleton('checkout/cart')->getQuote();
	}
	public function indexAction() {
		
		if (!Mage::getStoreConfig('onestepcheckout/config/allowguestcheckout')) {
			if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
				$this->_redirect('customer/account/login');
				return;
			}
		}
		if ($this->_initAction()) {
			
				if ($blocks=$this->getLayout()->getBlock('checkout.onepage')) {
					$blocks=$this->getLayout()->getBlock('checkout.onepage')->unsetChildren();
				}
			
			$this->renderLayout();
		}
		else
			$this->_redirect('checkout/cart');

	}

	public function _initAction() {
		if (!Mage::helper('checkout')->canOnepageCheckout()) {
			Mage::getSingleton('checkout/session')->addError($this->__('The onepage checkout is disabled.'));
			return false;
		}
		$quote = $this->getOnepage()->getQuote();
		if (!$quote->hasItems() || $quote->getHasError()) {
			return false;
		}
		if (!$quote->validateMinimumAmount()) {
			$error = Mage::getStoreConfig('sales/minimum_order/error_message');
			Mage::getSingleton('checkout/session')->addError($error);
			return false;
		}
		Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
		Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_secure'=>true)));
		$this->initInfoaddress();
		$this->getOnepage()->initCheckout();
		
		$defaultpaymentmethod=$this->initpaymentmethod();
		if ($defaultpaymentmethod) {
			try{
				Mage::getSingleton('checkout/session')->getQuote()->getShippingAddress()->setPaymentMethod($defaultpaymentmethod);
				$payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
				$payment->importData(array('method'=>$defaultpaymentmethod));
			}catch(Exception $e) {
			}
		}
		
		$applyrule=$this->getQuote()->getAppliedRuleIds();
		$applyaction=Mage::getModel('salesrule/rule')->load($applyrule)->getSimpleAction();

		if ($applyaction!='cart_fixed') {
			Mage::getSingleton('checkout/session')->getQuote()->setTotalsCollectedFlag(false);
		}
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->_initLayoutMessages('checkout/session');
		$this->_initLayoutMessages('catalog/session');
		Mage::getSingleton('catalog/session')->getData('messages')->clear();
		Mage::getSingleton('checkout/session')->getData('messages')->clear();
		$this->getLayout()->getBlock('head')->setTitle($this->__('Checkout'));

		return true;
	}
	public function initshippingmethod() {

		$listmethod='';
		$guessCustomer = Mage::getSingleton('checkout/session')->getQuote();
		$addresses=$guessCustomer->getShippingAddress();
		$applyrule=$this->getQuote()->getAppliedRuleIds();
		$applyaction=Mage::getModel('salesrule/rule')->load($applyrule)->getSimpleAction();

		if ($applyaction!='cart_fixed') {
			Mage::getSingleton('checkout/session')->getQuote()->setTotalsCollectedFlag(false);
		}
		$list_shipmethod=$addresses->getGroupedAllShippingRates();
		foreach ($list_shipmethod as $code => $_rates) {
			$listmethod[]=$code;
		}
		if (!$guessCustomer->isVirtual()) {
			if ($listmethod==null) {return;}
			if (sizeof($listmethod)==1) {
				return $listmethod[0].'_'.$listmethod[0];
			}else {
				foreach ($listmethod as $methodname) {
					if (Mage::getStoreConfig("onestepcheckout/config/default_shippingmethod")==$methodname.'_'.$methodname) {
						return $methodname.'_'.$methodname;
					}
				}
			}
		}
		return;
	}
	public function _canUseMethod($method) {
		if (!$method->canUseForCountry($this->getQuote()->getBillingAddress()->getCountry())) {
			return false;
		}

		if (!$method->canUseForCurrency(Mage::app()->getStore()->getBaseCurrencyCode())) {
			return false;
		}
		$total = Mage::getSingleton('checkout/session')->getQuote()->getBaseGrandTotal();
		$minTotal = $method->getConfigData('min_order_total');
		$maxTotal = $method->getConfigData('max_order_total');
		if ((!empty($minTotal) && ($total < $minTotal)) || (!empty($maxTotal) && ($total > $maxTotal))) {
			return false;
		}
		return true;
	}
	public function initpaymentmethod() {
		$listmethod='';
		$guessCustomer = Mage::getSingleton('checkout/session')->getQuote();
		$store = $guessCustomer ? $guessCustomer->getStoreId() : null;
		$methods = Mage::helper('payment')->getStoreMethods($store, $guessCustomer);
		$billingCountry = Mage::getSingleton('checkout/session')->getQuote()->getBillingAddress()->getCountryId();
		foreach ($methods as $key => $method) {
			if ($this->_canUseMethod($method)) {
				$listmethod[]=$method->getCode();
			}
		}
		try{
			if ($listmethod ==null or $listmethod=='') {return;}
			if (sizeof($listmethod)==1) {
				if($listmethod[0] != "moip_transparente_standard"){
					Mage::getSingleton('checkout/session')->addError("O meio de pagamento preferêncial deve ser o Transparente.");
				}
				return $listmethod[0];
			}else {
				foreach ($listmethod as $methodname) {
					if (Mage::getStoreConfig("onestepcheckout/config/default_paymentmethod")==$methodname) {
						if($methodname != "moip_transparente_standard"){
							Mage::getSingleton('checkout/session')->addError("O meio de pagamento preferêncial deve ser o Transparente.");
						}
						return $methodname;
					}
				}
			}
			return;
		}catch (Exception $e) {
			echo $e->getMessage();die;
		}
	}
	public function initInfoaddress() {
		$coutryid='';$postcode='';$region='';$regionid='';$city='';$customerAddressId='';
		$coutryid = 'BR';
		
		$postData=array(
			'address_id'=>'',
			'firstname'=>'',
			'lastname'=>'',
			'company'=>'',
			'email'=>'',
			'street'=>array('', '', '', ''),
			'city'=>'',
			'region_id'=>'',
			'region'=>'',
			'postcode'=>'00000-000',
			'country_id'=>'BR',
			'telephone'=>'',
			'fax'=>'',
			'save_in_address_book'=>'0'
		);
		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			$customerAddressId =Mage::getSingleton('customer/session')->getCustomer()->getDefaultBilling();
		}

			$postData = $this->filterdata($postData);
			if (version_compare(Mage::getVersion(), '1.4.0.1', '>=')){
					if (isset($postData['email'])) 
						$postData['email'] = trim($postData['email']);
					$data = $this->_filterPostData($postData);
			}
			
			else{
					if (isset($postData['email'])) 
						$postData['email'] = trim($postData['email']);
					$data=$postData;
			}

		if (($postData['country_id']!='')  || $customerAddressId) {
			$this->saveBilling($data, $customerAddressId);
			$this->saveShipping($data, $customerAddressId);
		}
		else {
			
			$this->_getQuote()->getShippingAddress()
			->setCountryId('')
			->setPostcode('')
			->setCollectShippingRates(true);
			$this->_getQuote()->save();
			$this->loadLayout()->renderLayout();
			return;
		}
		
	}
	public function saveShipping($data, $customerAddressId) {
		if (empty($data)) {
			return array('error' => -1, 'message' => $this->_helper->__('Invalid data.'));
		}
		$address = $this->getQuote()->getShippingAddress();
		if (!empty($customerAddressId)) {
			$customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
			if ($customerAddress->getId()) {
				if ($customerAddress->getCustomerId() != $this->getQuote()->getCustomerId()) {
					return array('error' => 1,
						'message' => $this->_helper->__('Customer Address is not valid.')
					);
				}
				$address->importCustomerAddress($customerAddress);
			}
		} else {
			unset($data['address_id']);
			$address->addData($data);
		}
		$address->implodeStreetAddress();
		$address->setCollectShippingRates(true);
	}
	public function saveBilling($data, $customerAddressId) {
		if (empty($data)) {
			return array('error' => -1, 'message' => $this->_helper->__('Invalid data.'));
		}
		$address = $this->getQuote()->getBillingAddress();
		if (!empty($customerAddressId)) {
			$customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
			if ($customerAddress->getId()) {
				if ($customerAddress->getCustomerId() != $this->getQuote()->getCustomerId()) {
					return array('error' => 1,
						'message' => $this->_helper->__('Customer Address is not valid.')
					);
				}
				$address->importCustomerAddress($customerAddress);
			}
		}
		else {
			unset($data['address_id']);
			$address->addData($data);
		}
		$address->implodeStreetAddress();
		if (!$this->getQuote()->isVirtual()) {
			$shipping = $this->getQuote()->getShippingAddress();
			$shipping->setSameAsBilling(0);

		}
	}
	protected function _processValidateCustomer(Mage_Sales_Model_Quote_Address $address) {
		$dob = '';
		if ($address->getDob()) {
			$dob = Mage::app()->getLocale()->date($address->getDob(), null, null, false)->toString('yyyy-MM-dd');
			$this->getQuote()->setCustomerDob($dob);
		}
		if ($address->getTaxvat()) {
			$this->getQuote()->setCustomerTaxvat($address->getTaxvat());
		}
		if ($address->getGender()) {
			$this->getQuote()->setCustomerGender($address->getGender());
		}
		if ($this->getQuote()->getCheckoutMethod()=='register') {
			$customer = Mage::getModel('customer/customer');
			$this->getQuote()->setPasswordHash($customer->encryptPassword($address->getCustomerPassword()));
			foreach (array(
					'firstname'    => 'firstname',
					'lastname'     => 'lastname',
					'email'        => 'email',
					'password'     => 'customer_password',
					'confirmation' => 'confirm_password',
					'taxvat'       => 'taxvat',
					'gender'       => 'gender',
				) as $key => $dataKey) {
				$customer->setData($key, $address->getData($dataKey));
			}
			if ($dob) {
				$customer->setDob($dob);
			}
			$validationResult = $customer->validate();
			if (true !== $validationResult && is_array($validationResult)) {
				return array(
					'error'   => -1,
					'message' => implode(', ', $validationResult)
				);
			}
		} elseif (self::METHOD_GUEST == $this->getQuote()->getCheckoutMethod()) {
			$email = $address->getData('email');
			if (!Zend_Validate::is($email, 'EmailAddress')) {
				return array(
					'error'   => -1,
					'message' => $this->_helper->__('Invalid email address "%s"', $email)
				);
			}
		}
		return true;
	}
	public function updateshippingmethodAction() {
		$data = $this->getRequest()->getPost('shipping_method', '');
		$result = $this->getOnepage()->saveShippingMethod($data);
		if (!$result) {
			Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method',
				array('request'=>$this->getRequest(),
					'quote'=>$this->getOnepage()->getQuote()));
			$this->getOnepage()->getQuote()->collectTotals();
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		}
		$this->getOnepage()->getQuote()->collectTotals()->save();
		$this->loadLayout()->renderLayout();
	}
	public function updatepaymentmethodAction() {
		$data=$this->getRequest()->getPost('payment');
		try{
			$this->getOnepage()->savePayment($data);
		}
		catch (Exception $e) {
			$this->_getQuote()->save();
		}
		$this->loadLayout()->renderLayout();
	}
	public function updateemailmsgAction() {
		$email = (string) $this->getRequest()->getParam('email');
		$websiteid=Mage::app()->getWebsite()->getId();
		$store=Mage::app()->getStore();
		$customer=Mage::getSingleton('customer/customer');
		$customer->website_id=$websiteid;
		$customer->setStore($store);
		$customer->loadByEmail($email);
		if ($customer->getId()) {
			echo "0";
		}
		else {
			echo "1";
		}
		return;
	}
	public function removeproductAction() {
		$id = (int) $this->getRequest()->getParam('id');
		$hasgiftbox=$this->getRequest()->getParam('hasgiftbox');
		if ($id) {
			try {
				Mage::getSingleton('checkout/cart')->removeItem($id)->save();
			} catch (Exception $e) {
				$success=0;
				echo '{"r":"'.$success.'","error":"'.Mage::helper('onestepcheckout')->__('Cannot remove the item.').'","view":' .json_encode($this->renderReview()).'}';die;
				return ;
			}
		}
		$success=1;
		if (!$this->_getQuote()->getItemsCount()) {
			echo '{"r":"'.$success.'","view":"<script type=\"text/javascript\">window.location=\"'.Mage::getUrl('checkout/onepage').'\"</script>"}';die;
			return;
		}
		else {
			if ($hasgiftbox) {
				echo '{"r":"'.$success.'","view":' .json_encode($this->renderReview()).',"giftbox":' .json_encode($this->renderGiftbox()) .'}';die;
				return ;
			}
			else {
				echo '{"r":"'.$success.'","view":' .json_encode($this->renderReview()).'}';die;
				return ;
			}
		}
	}
	
	public function updatecouponAction() {
		$this->_initLayoutMessages('checkout/session');
		if (!$this->_getQuote()->getItemsCount()) {

			echo '{"r":"0","coupon":'.json_encode($this->renderCoupon()).',"view":' . json_encode($this->renderReview()).'}';die;
			return;
		}
		$couponCode = (string) $this->getRequest()->getParam('coupon_code');
		if ($this->getRequest()->getParam('remove') == 1) {
			$couponCode = '';
		}
		$oldCouponCode = $this->_getQuote()->getCouponCode();
		if (!strlen($couponCode) && !strlen($oldCouponCode)) {
			echo '{"r":"0","coupon":'.json_encode($this->renderCoupon()).',"view":' . json_encode($this->renderReview()).'}';die;
			return;
		}
		try {
			$this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
			$this->_getQuote()->setCouponCode(strlen($couponCode) ? $couponCode : '')
			->collectTotals()
			->save();
			if ($couponCode) {
				if ($couponCode == $this->_getQuote()->getCouponCode()) {
					Mage::getSingleton('checkout/session')->addSuccess(
						$this->__('Coupon code "%s" was applied.', Mage::helper('core')->htmlEscape($couponCode))
					);
				}
				else {
					Mage::getSingleton('checkout/session')->addError(
						$this->__('Coupon code "%s" is not valid.', Mage::helper('core')->htmlEscape($couponCode))
					);
				}
			} else {
				Mage::getSingleton('checkout/session')->addSuccess($this->__('Coupon code was canceled.'));
			}
		}
		catch (Mage_Core_Exception $e) {
			Mage::getSingleton('checkout/session')->addError($e->getMessage());
		}
		catch (Exception $e) {
			Mage::getSingleton('checkout/session')->addError($this->__('Cannot apply the coupon code.'));
		}
		$this->_initLayoutMessages('checkout/session');
		$success=1;
		echo '{"r":"'.$success.'","coupon":'.json_encode($this->renderCoupon()).',"view":' .json_encode($this->renderReview()).'}';die;
		return ;
	}
	public function renderReview() {
		$layout=$this->getLayout();
		$update = $layout->getUpdate();
		$update->load('checkout_onepage_index');
		$layout->generateXml();
		$layout->generateBlocks();
		$output=$layout->getBlock('info')->toHtml();
		return $output;
	}
	public function renderCoupon() {
		$layout=$this->getLayout();
		$update = $layout->getUpdate();
		$update->load('checkout_onepage_index');
		$layout->generateXml();
		$layout->generateBlocks();

		$output = $layout->getBlock('checkout.onepage.coupon')->toHtml();
		return $output;
	}
	public function renderGiftbox() {
		$layout=$this->getLayout();
		$update = $layout->getUpdate();
		$update->load('checkout_onepage_index');
		$layout->generateXml();
		$layout->generateBlocks();
		$output = $layout->getBlock('onestepcheckout.onepage.shipping_method.additional')->toHtml();
		return $output;
	}
	public function updatepaymenttypeAction() {
		if ($this->notshiptype==1) {
			$this->loadLayout()->renderLayout();
		}
		else {
			$this->updateshippingtypeAction();
		}
	}
	public function updateshippingtypeAction() {
		$this->notshiptype=1;
		if ($this->getRequest()->isPost()) {
			$isbilling="billing";
			if ($this->getRequest()->getPost('ship_to_same_address')=="1") {
				$isbilling="billing";
			}
			else {
				$isbilling="shipping";
			}
			$postData=$this->getRequest()->getPost($isbilling, array());
			$customerAddressId = $this->getRequest()->getPost($isbilling.'_address_id', false);
			
			if (($postData['country_id']!='')  or $customerAddressId) {

				$postData = $this->filterdata($postData);
				$postData['use_for_shipping']='1';
				if (version_compare(Mage::getVersion(), '1.4.0.1', '>='))
					$data = $this->_filterPostData($postData);
				else
					$data=$postData;
				if (isset($data['email'])) {
					$data['email'] = trim($data['email']);
				}
				if ($isbilling="billing") {
					$result = $this->getOnepage()->saveBilling($data, '');}
				else
					$result = $this->getOnepage()->saveShipping($data, '');
			}
			
		}
		$this->_getQuote()->getShippingAddress()
			->setCountryId('BR')
			->setPostcode($postData['postcode'])
			->setCollectShippingRates(true);
			$this->_getQuote()->save();
			$this->loadLayout()->renderLayout();
	}
	
	public function _getSession() {
		Mage::getSingleton('customer/session');
	}
	public function updateloginAction() {
		$email=$this->getRequest()->getPost('email');
		$password=$this->getRequest()->getPost('password');
		if ($this->isCustomerLoggedIn()) {
			$this->_redirect('*/*/');
			return;
		}
		if ($this->getRequest()->isPost()) {
			if (!empty($email) && !empty($password)) {
				try{
					Mage::getSingleton('customer/session')->login($email, $password);
				}catch(Mage_Core_Exception $e) {
				}catch(Exception $e) {
				}
			}
		}
		if (Mage::getSingleton('customer/session')->isLoggedIn()) {
			echo "1";
		}
		else {
			echo "0";
		}
	}
	public function forgotpassAction() {
		$email=$this->getRequest()->getPost('email');
		$emailerror="0";
		if ($email) {
			if (!Zend_Validate::is($email, 'EmailAddress')) {
				$this->_getSession()->setForgottenEmail($email);
				$emailerror="0";
				echo $emailerror;
				return $emailerror;
			}
			$customer = Mage::getModel('customer/customer')
			->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
			->loadByEmail($email);
			if ($customer->getId()) {
				try {
					$newPassword = $customer->generatePassword();
					$customer->changePassword($newPassword, false);
					$customer->sendPasswordReminderEmail();
					$emailerror="1";
					echo $emailerror;
					return $emailerror;
				}
				catch (Exception $e) {
				}
			}
			else {
				$emailerror="2";
				Mage::getSingleton('customer/session')->setForgottenEmail($email);
				echo $emailerror;
				return;
			}
		} else {
			$emailerror="0";
			echo $emailerror;
			return $emailerror;
		}
	}
	public function updateordermethodAction() {
		if (!$this->isCustomerLoggedIn()) {
			$result_save_method = $this->getOnepage()->saveCheckoutMethod('register');
		}
		if ($this->getRequest()->isPost()) {
			if ($this->isCustomerLoggedIn()) {
				$postData = $this->getRequest()->getPost('billing', array());
				$_dob = $this->getLayout()->createBlock('customer/widget_dob');
				$_gender = $this->getLayout()->createBlock('customer/widget_gender');
				$_taxvat = $this->getLayout()->createBlock('customer/widget_taxvat');
				$customer =  Mage::getSingleton('customer/session')->getCustomer();
				$customerForm = Mage::getModel('customer/form');
				$customerForm->setFormCode('customer_account_edit')->setEntity($customer);
				if ($_dob->isEnabled()) {
					$dob = $postData['dob'];
					$customer->setDob($dob);
				}
				if ($_gender->isEnabled()) {
					$gender = $postData['gender'];
					$customer->setGender($gender);
				}

				if ($_taxvat->isEnabled()) {
					$taxvat = $postData['taxvat'];
					$customer->setTaxvat($taxvat);
				}
				
					
				if (isset($postData['suffix']) && $customer->getSuffix()=='' ) {
					$suffix =  $postData['suffix'];
					$customer->setSuffix($suffix);
				}

				if (isset($postData['prefix']) && $customer->getPrefix()=='' ) {
					$prefix =  $postData['prefix'];
					$customer->setPrefix($prefix);
				}
				if (isset($postData['middlename']) && $customer->getMiddlename()=='' ) {
					$middle =  $postData['middlename'];
					$customer->setMiddlename($middle);
				}
				$customer->save();
			}
		
				$data_save_billing = $this->getRequest()->getPost('billing', array());

			if ($this->isCustomerLoggedIn()) {
				$this->saveAddress('billing', $data_save_billing);
			}
			$request_data = Mage::app()->getRequest()->getPost();
			
						
				$customerAddressId  = "";			
			
			
			if (isset($data_save_billing['email'])) {
				$data_save_billing['email'] = trim($data_save_billing['email']);
			}
			if ($this->getRequest()->getPost('subscribe_newsletter')=='1') {
				if ($this->isCustomerLoggedIn()) {
					$customer = Mage::getSingleton('customer/session')->getCustomer();
					$customer->setIsSubscribed(1);
				}else {
					$this->savesubscibe($data_save_billing['email']);
				}
			}
			$result_save_billing = $this->getOnepage()->saveBilling($data_save_billing, $customerAddressId);

		}
		$isclick=$this->getRequest()->getPost('ship_to_same_address');
		$ship="billing";
		if (!$isclick=='1') {
			$ship="shipping";
		}

		if ($this->getrequest()->ispost()) {
			$data_save_shipping = $this->getrequest()->getpost($ship, array());
			if ($this->isCustomerLoggedIn() && !$isclick) {
				$this->saveAddress('shipping', $data_save_shipping);
			}


			if ($isclick=='1') {
				$data_save_shipping['same_as_billing']=1;
			}
			$customeraddressid = $this->getrequest()->getpost($ship.'_address_id', false);
			if ($isclick || ($this->getRequest()->getPost('shipping_address_id') != "")) {
				$customeraddressid  = "";
			}

			$result_save_shipping = $this->getonepage()->saveshipping($data_save_shipping, $customeraddressid);
		}
		if ($this->getRequest()->isPost()) {
			$data_save_shipping_method = $this->getRequest()->getPost('shipping_method', '');
			$result_save_shipping_method = $this->getOnepage()->saveShippingMethod($data_save_shipping_method);
			if (!$result_save_shipping_method) {
				Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', array('request'=>$this->getRequest(), 'quote'=>$this->getOnepage()->getQuote()));
			}
		}
		$result_savepayment = array();
		$data_savepayment = $this->getRequest()->getPost('payment', array());
		try{
			$result_savepayment = $this->getOnepage()->savePayment($data_savepayment);
		} catch(Exception $e) {
			$message = $e->getMessage();
			Mage::getSingleton('checkout/session')->addError($this->__($message));
			$this->_redirect('checkout/onepage/index');
			return;
		}
		$redirectUrl = $this->getOnepage()->getQuote()->getPayment()->getCheckoutRedirectUrl();
		if (isset($redirectUrl)) {
			$this->_redirectUrl($redirectUrl);
			return;
		}
		$result_order = array();
		if ($data_order = $this->getRequest()->getPost('payment', false)) {
			$this->getOnepage()->getQuote()->getPayment()->importData($data_order);
		}
		try{
			$this->getOnepage()->saveOrder();
		}
		catch (Exception $e) {
			echo $e->getMessage();
			echo "<script type='text/javascript'>setTimeout('window.location=\"".Mage::getUrl('checkout/onepage')."\"',2000)</script>";
			return;
		}
		$session = $this->getOnepage()->getCheckout();
		$lastOrderId = $session->getLastOrderId();
		$data_customercomment ="";
		if ($this->getrequest()->ispost()) {
			$data_customercomment = $this->getrequest()->getpost('onestepcheckout_comments');
			$order=Mage::getModel('onestepcheckout/onestepcheckout');
			$order->setSalesOrderId($lastOrderId);
			$order->setMoipCustomercommentInfo($data_customercomment);
			
			$order->save();
		}
		$redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
		$result_order['success'] = true;
		$result_order['error']   = false;
		$cart = Mage::getModel('checkout/cart');
		$cartItems = $cart->getItems();
		foreach ($cartItems as $item) {
			$cart->removeItem($item->getId())->save();
		}
		if (isset($redirectUrl)) {
			$this->_redirectUrl($redirectUrl);
			return;
		}
		$this->_redirect('checkout/onepage/success');
	}

	protected function filterdata($data, $filter="true") {

		$arrayname=array('address_id', 'firstname', 'lastname', 'company', 'email', 'city', 'region_id', 'region', 'postcode', 'country_id', 'telephone', 'fax','tipopessoa','nomefantasia','razaosocial','cnpj','insestadual', 'save_in_address_book');
		$filterdata=array();
		if ($filter =='true') {
			if (version_compare(Mage::getVersion(), '1.4.2.0', '>=')) {
				$filterdata=array(
					'prefix'=>'n/a',
					'address_id'=>'n/a',
					'firstname'=>'n/a',
					'lastname'=>'n/a',
					'company'=>'n/a',
					'email'=>'n/a@na.na',
					'street'=>array('n/a', 'n/a', 'n/a', 'n/a'),
					'city'=>'n/a',
					'region_id'=>'n/a',
					'region'=>'n/a' ,
					'postcode'=>'n/a',
					'country_id'=>'n/a',
					'telephone'=>'n/a',
					'fax'=>'n/a',
					'month'=> null,
					'day'=>null,
					'year'=>null,
					'dob'=>'01/01/1900',
					'gender'=>'n/a',
					'taxvat'=>'n/a',
					'suffix'=>'n/a',
					'tipopessoa'=>'n/a',
					'nomefantasia'=>'n/a',
					'razaosocial'=>'n/a',
					'cnpj'=>'n/a',
					'insestadual'=>'n/a',
					'save_in_address_book'=>''
				);
			}
			else {

				$filterdata=array(
					'prefix'=>'n/a',
					'address_id'=>'n/a',
					'firstname'=>'n/a',
					'lastname'=>'n/a',
					'company'=>'n/a',
					'email'=>'n/a',
					'street'=>array(
						'street1'=>'street is null',
						'street2'=>'street is null',
						'street3'=>'street is null',
						'street4'=>'street is null'
					),
					'city'=>'n/a',
					'region_id'=>'n/a',
					'region'=>'n/a' ,
					'postcode'=>'.',
					'country_id'=>'n/a',
					'telephone'=>'n/a',
					'fax'=>'n/a',
					'month'=> null,
					'day'=>null,
					'year'=>null,
					'dob'=>'01/01/1900',
					'gender'=>'n/a',
					'taxvat'=>'n/a',
					'suffix'=>'n/a',
					'tipopessoa'=>'n/a',
					'nomefantasia'=>'n/a',
					'razaosocial'=>'n/a',
					'cnpj'=>'n/a',
					'insestadual'=>'n/a',
					'save_in_address_book'=>''
				) ;
			}
		}
		else {
			$filterdata=array(
				'prefix'=>'',
				'address_id'=>'',
				'firstname'=>'',
				'lastname'=>'',
				'company'=>'',
				'email'=>'',
				'street'=>array('', '', '', ''),
				'city'=>'',
				'region_id'=>'',
				'region'=>'' ,
				'postcode'=>'.',
				'country_id'=>'',
				'telephone'=>'',
				'fax'=>'',
				'month'=> null,
				'day'=>null,
				'year'=>null,
				'dob'=>null,
				'gender'=>'',
				'taxvat'=>'',
				'suffix'=>'',
				'tipopessoa'=>'',
				'nomefantasia'=>'',
				'razaosocial'=>'',
				'cnpj'=>'',
				'insestadual'=>'',
				'save_in_address_book'=>''
			);
		}
		foreach ($data as $item=>$value) {
			if (!is_array($value)) {
				if ($value!='')
					$filterdata[$item]=$value;
			}
			else {
				$street=$value;
				
				if (isset($street[0])){

					if(isset($street[1]) || $street[1] =="."){
					$street_1 = $street[1];
					} else {
						$street_2 = "";
					}

					if(isset($street[2])){
					$street_2 = $street[2];
					} else {
						$street_2 = "";
					}
					if(isset($street[3])){
					$street_3 = $street[3];
					} else {
						$street_3 = "";
					}

					
						$filterdata[$item]=array($street[0], $street_1,$street_2,$street_3);
				}
				
			}
		}
		return $filterdata;
	}

	public function isCustomerLoggedIn() {
		return Mage::getSingleton('customer/session')->isLoggedIn();
	}

	
	public function savesubscibe($mail) {
		if ($mail) {
			if (version_compare(Mage::getVersion(), '1.3.2.4', '>')) {
				$session            = Mage::getSingleton('checkout/session');
				$customerSession    = Mage::getSingleton('customer/session');
				$email              = (string) $mail;
				try {
					if (!Zend_Validate::is($email, 'EmailAddress')) {
						Mage::throwException($this->__('Please enter a valid email address.'));
					}
					if (Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG) != 1 &&
						!$customerSession->isLoggedIn()) {
						Mage::throwException($this->__('Sorry, but administrator denied subscription for guests. Please <a href="%s">register</a>.', Mage::getUrl('customer/account/create/')));
					}
					$ownerId = Mage::getModel('customer/customer')
					->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
					->loadByEmail($email)
					->getId();
					if ($ownerId !== null && $ownerId != $customerSession->getId()) {
						Mage::throwException($this->__('Sorry, but your can not subscribe email adress assigned to another user.'));
					}
					$status = Mage::getModel('newsletter/subscriber')->subscribe($email);
					if ($status == Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE) {
						$session->addSuccess($this->__('Confirmation request has been sent.'));
					}
					else {
						$session->addSuccess($this->__('Thank you for your subscription.'));
					}
				}
				catch (Mage_Core_Exception $e) {
					$session->addException($e, $this->__('There was a problem with the subscription: %s', $e->getMessage()));
				}
				catch (Exception $e) {
					$session->addException($e, $this->__('There was a problem with the subscription.'));
				}
			}
			else {
				if ($this->getRequest()->isPost() && $this->getRequest()->getPost('email')) {
					$session   = Mage::getSingleton('core/session');
					$email     = (string) $this->getRequest()->getPost('email');

					try {
						if (!Zend_Validate::is($email, 'EmailAddress')) {
							Mage::throwException($this->__('Please enter a valid email address'));
						}

						$status = Mage::getModel('newsletter/subscriber')->subscribe($email);
						if ($status == Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE) {
							$session->addSuccess($this->__('Confirmation request has been sent'));
						}
						else {
							$session->addSuccess($this->__('Thank you for your subscription'));
						}
					}
					catch (Mage_Core_Exception $e) {
						$session->addException($e, $this->__('There was a problem with the subscription: %s', $e->getMessage()));
					}
					catch (Exception $e) {
						$session->addException($e, $this->__('There was a problem with the subscription'));
					}
				}
			}
		}

	}
	public function updatebillingformAction() {
		$this->updatebillingform();
	}

	public function updatesortbillingformAction() {
		$this->updatebillingform();
	}
	public function updateshippingformAction() {

		$this->updateshippingform();
	}

	public function updatesortshippingformAction() {
		$this->updateshippingform();
	}

	public function updateshippingform() {
		if ($this->getRequest()->isPost()) {
			$postData=$this->getRequest()->getPost();
			$customerAddressId = $postData['shipping_address_id'];
			if (intval($customerAddressId)!=0) {
				$postData = $this->filterdata($postData);
				if (version_compare(Mage::getVersion(), '1.4.0.1', '>='))
					$data = $this->_filterPostData($postData);
				else
					$data=$postData;
				$result = $this->getOnepage()->saveShipping($data, $customerAddressId);
			}
		}
		$this->loadLayout()->renderLayout();

	}
	

	public function updatebillingform() {
		if ($this->getRequest()->isPost()) {
			$billing_data = $this->getRequest()->getPost('billing', array());
			$postData=$this->getRequest()->getPost();
			$customerAddressId = $postData['billing_address_id'];
			if (intval($customerAddressId)!=0) {
				$data = $postData;
				$billingAddressId = $this->getRequest()->getPost('billing_address_id');
				$result = $this->getOnepage()->saveBilling($billing_data, $billingAddressId);
			}
			else {

			}
			$this->loadLayout()->renderLayout();
		}
		
	}
	 public function saveAddress($type,$data)
    {		 	
    			
			 	$addressId = $this->getRequest()->getPost($type.'_address_id');
			 	
			 	$save_address = $this->getRequest()->getPost('save_in_address_book');
			 	
			 	$customer = Mage::getSingleton('customer/session')->getCustomer();				          
				$address  = Mage::getModel('customer/address');

			 	if(!Mage::getSingleton('customer/session')->getCustomer()->getDefaultBilling())
			 	{			 		
			 		
			 			   // Save data				      
				           
				            
				            
				            $errors = array();				
				            /* @var $addressForm Mage_Customer_Model_Form */
				           $addressForm = Mage::getModel('customer/form');
				           $addressForm->setFormCode('customer_address_edit')
				               ->setEntity($address);
				           $addressData    = $this->getRequest()->getPost($type, array());//$addressForm->extractData($this->getRequest());
				           try {
				                $addressForm->compactData($addressData);
				                $address->setCustomerId($customer->getId())
				                    ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', true))
				                    ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', true))->setSaveInAddressBook('1');
				
				                
				                
				           
				                    $address->save();
				                    
				               
				            } catch (Mage_Core_Exception $e) {
				               Zend_Debug::dump($ex->getMessage());
				            }
			 	} 
			 	

			 	if(Mage::getSingleton('customer/session')->getCustomer()->getDefaultBilling() && $save_address) {
			 		
			 		
				 		$customer = Mage::getSingleton('customer/session')->getCustomer();	
				 		$customAddress = Mage::getModel('customer/address');
						$addressData    = $this->getRequest()->getPost($type, array());
						$customAddress->setData($addressData)
						            ->setCustomerId($customer->getId())
						            ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
						            ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false))
						            ->setSaveInAddressBook('1');
						try {
						    $customAddress->save();
						}
						catch (Exception $ex) {
						   Zend_Debug::dump($ex->getMessage());
						}
				 	
			 	}
	}
}
