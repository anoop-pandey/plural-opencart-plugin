<?php
class Controllerextensionpaymentpinepg extends Controller {
  
  public function index() {
    $this->load->language('extension/payment/pinepg');
	$this->logger = new Log('pinepg_'. date("Y-m-d").'.log');
    $this->load->model('checkout/order');
	$this->load->model('catalog/product');
	$Order_Id=$this->session->data['order_id'];
    $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
    
    $this->logger->write('[Order ID]:' . $Order_Id.'  Order Info: ' . serialize($order_info));
    
    if ($order_info) {    

		$data['ppc_MerchantID'] = $this->config->get('payment_pinepg_merchantid');
		$data['ppc_MerchantAccessCode'] = $this->config->get('payment_pinepg_access_code');
		$secret_key   =   $this -> Hex2String($this->config->get('payment_pinepg_secure_secret'));
		$data['ppc_PayModeOnLandingPage'] = $this->config->get('payment_pinepg_payment_mode');
		$data['ppc_LPC_SEQ'] = '1';

		$data['ppc_NavigationMode'] = '2';
		$data['ppc_MerchantReturnURL'] =  $this->url->link('extension/payment/pinepg/callback');
		$data['ppc_TransactionType'] = '1';
		$data['ppc_UniqueMerchantTxnID'] =$this->session->data['order_id'] . '_' . date("ymdHis");
		$data['ppc_CustomerMobile'] =html_entity_decode($order_info['telephone'], ENT_QUOTES, 'UTF-8') ;
		$data['ppc_CustomerEmail'] =$order_info['email'] ;
	
		$data['ppc_CustomerFirstName'] =$order_info['payment_firstname'] ;
		$data['ppc_CustomerLastName'] =$order_info['payment_lastname'] ;
		$data['ppc_CustomerCity'] =$order_info['payment_city'] ;
		$data['ppc_CustomerState'] =$order_info['payment_zone'] ;
		$data['ppc_CustomerCountry'] =$order_info['payment_country'] ;
		$data['ppc_CustomerAddress1'] =$order_info['payment_address_1'] ;
		$data['ppc_CustomerAddress2'] =$order_info['payment_address_2'] ;
		$data['ppc_CustomerAddressPIN'] =$order_info['payment_postcode'] ;

		$data['ppc_ShippingFirstName'] =$order_info['shipping_firstname'] ;
		$data['ppc_ShippingLastName'] =$order_info['shipping_lastname'] ;
		$data['ppc_ShippingAddress1'] =$order_info['shipping_address_1'] ;
		$data['ppc_ShippingAddress2'] =$order_info['shipping_address_2'] ;
		$data['ppc_ShippingCity'] =$order_info['shipping_city'] ;
		$data['ppc_ShippingState'] =$order_info['shipping_zone'] ;
		$data['ppc_ShippingCountry'] =$order_info['shipping_country'] ;
		$data['ppc_ShippingZipCode'] =$order_info['shipping_postcode'] ;

		$data['ppc_UdfField1'] = 'OpenCart_v_3.0.3.2';
		$data['ppc_Product_Code'] = '';
		$data['ppc_MerchantProductInfo'] = '';


		$product_id ='';
		$totalOrders = 0;
		$IsProductQuantityInCartMoreThanOne=false;

		$currency_value = $order_info['currency_value'] * 100;
        $item_total = 0;

        foreach ($this->cart->getProducts() as $product) {

			$product_price = $product['price'] * $currency_value;

			$item_total += $product_price * $product['quantity'];

			$shipping_total = 0;
			
			if (isset($this->session->data['shipping_method'])) {
				$shipping_total = $this->tax->calculate($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id'], $this->config->get('config_tax'));
				$shipping_total = $shipping_total * $currency_value;
			}
			
			$orderamount = $order_info['total'] * $currency_value;

			if (is_numeric($orderamount) && floor($orderamount) != $orderamount) {
				$orderamount = ceil($orderamount);
			}

			$data['ppc_Amount'] = $orderamount;

            $product_id = $product['product_id'];
			if($product['quantity']>1)
			{
				$IsProductQuantityInCartMoreThanOne=true;
				break;
			}
            $totalOrders +=1;
        }

		$this->logger->write('Order Total Amount :' . $orderamount);

		$productData = $this->model_catalog_product->getProduct($product_id);

        if ($totalOrders == 1 && $IsProductQuantityInCartMoreThanOne==false )
        {
            $data['ppc_Product_Code']  = $productData['sku'];
            $data['ppc_MerchantProductInfo'] = $product['name'];
        }
        else
        {
			$this->logger->write('[Order ID]:' . $Order_Id.'  More than one item in cart.So EMI is not applicable');
			$data['ppc_Product_Code']=  $productData['sku'];
            
        }
	
		ksort($data);
		$strString="";
	 
		// convert dictionary key and value to a single string variable
		foreach ($data as $key => $val) {
			 $strString.=$key."=".$val."&";
		}
 
		// trim last character from string
		$strString = substr($strString, 0, -1);



		$code = strtoupper(hash_hmac('sha256', $strString, $secret_key));

		  
		$data['ppc_DIA_SECRET_TYPE'] = 'SHA256';
		$data['ppc_DIA_SECRET'] = $code;	
		ksort($data);
		$strString="";
		// convert dictionary key and value to a single string variable
		foreach ($data as $key => $val) {
			 $strString.=$key."=".$val."&";
		}
	 
		$this->logger->write('[Order ID]:' . $Order_Id.'  Paramters send to Pine PG: ' .$strString);
		$PinePgMode=$this->config->get('payment_pinepg_mode');
		if($PinePgMode == "live")
		{
		  
		   $data['action'] = 'https://pinepg.in/pinepgredirect/index';
		   $this->logger->write('[Order ID]:' . $Order_Id.'  Redirect to Live URL of PINE PG: ' . $data['action']);
		}
		else
		{
		    $data['action'] = 'https://uat.pinepg.in/pinepgredirect/index';
			$this->logger->write('[Order ID]:' . $Order_Id.'  Redirect to Test URL of PINE PG: ' . $data['action']);
		}
	  
		$data['button_confirm'] = $this->language->get('button_confirm');
	

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/payment/pinepg')){
			return $this->load->view($this->config->get('config_template') . 'extension/payment/pinepg', $data);
		} else {
			return $this->load->view('extension/payment/pinepg', $data);
		}		
    }
  }
  
  public function Hex2String($hex){
            $string='';
            for ($i=0; $i < strlen($hex)-1; $i+=2){
                $string .= chr(hexdec($hex[$i].$hex[$i+1]));
            }
            return $string;
        }
		

    public function callback() 
	{
		
		$this->logger = new Log('pinepg_'. date("Y-m-d").'.log');
		$this->logger->write('Callback() called');
		if (isset($this->request->post['ppc_UniqueMerchantTxnID'])) 
		{
			$merchantTxnID = $this->request->post['ppc_UniqueMerchantTxnID'];

			$order_id = explode('_', $merchantTxnID);
			$order_id = (int)$order_id[0];    //get rid of time part

		  $this->logger->write('Order ID received: '.$order_id);
		} 
		else 
		{
			$this->logger->write('Received order id is null: ');
		  die('Illegal Access ORDER ID NOR PASSED');
		}
	
    $this->load->model('checkout/order');
    $order_info = $this->model_checkout_order->getOrder($order_id);
    if ($order_info) 
	{
		if ( !empty($_POST) ) 
		{
			
		$DiaSecretType='';
		$DiaSecret='';
	    if (isset($this->request->post['ppc_DIA_SECRET_TYPE'])) {
			$DiaSecretType = $this->request->post['ppc_DIA_SECRET_TYPE'];
		} 
		if (isset($this->request->post['ppc_DIA_SECRET'])) {
			$DiaSecret = $this->request->post['ppc_DIA_SECRET'];
		} 
		
		$strString="";
		ksort($_POST);
		foreach ($_POST as $key => $value)
		{
			$strString.=$key."=".$value."&";
		}
		$this->logger->write('[Order ID]:' . $order_id.' Received parameters : '.$strString);
		unset($_POST['ppc_DIA_SECRET_TYPE']);
		unset($_POST['ppc_DIA_SECRET']);
		$strString="";
		$secret_key   =   $this -> Hex2String($this->config->get('payment_pinepg_secure_secret'));
		ksort($_POST);
		foreach ($_POST as $key => $value)
		{
			$strString.=$key."=".$value."&";
		}
		
		
		$strString = substr($strString, 0, -1);
		$SecretHashCode = strtoupper(hash_hmac('sha256', $strString, $secret_key));
		$this->logger->write('[Order ID]:' . $order_id.'  Generated Secure hash of Received parameters ' .$SecretHashCode);
		if("" == trim($DiaSecret))
		{
			$this->logger->write('[Order ID]:' . $order_id.'  Transaction failed.Pine PG Secure hash is empty');
			 $comment='Transaction failed.Pine PG Secure hash is empty';
		     $Order_Status='10';
			$this->model_checkout_order->addOrderHistory($order_id, $Order_Status,$comment,true,false);
			$this->response->redirect($this->url->link('checkout/failure', 'path=59'));
		}   
		else
		{
			if(trim($DiaSecret)==trim($SecretHashCode))
			{
				$this->logger->write('[Order ID]:' . $order_id.'     Secure Hash is matched ');
					  $Order_Status='10'; 	
					  if ($this->request->post['ppc_PinePGTxnStatus'] == '4' && $this->request->post['ppc_TxnResponseCode'] == '1') 
					  {
						$this->logger->write('[Order ID]:' . $order_id.'  Payment Transation is successfulL '.$this->request->post['ppc_UniqueMerchantTxnID']);
						$comment='Payment Transation is successful. Edge Payment ID: '.$this->request->post['ppc_UniqueMerchantTxnID'];
						$Order_Status='2';
						
					  }
					  else if($this->request->post['ppc_PinePGTxnStatus'] == '-10')
					  {
						  $this->logger->write('[Order ID]:' . $order_id.'  Transaction cancelled by user ');
						  $comment='Transaction cancelled by user';
						  $Order_Status='7';
					  }
					   else if($this->request->post['ppc_PinePGTxnStatus'] == '-6')
					  {
						   $this->logger->write('[Order ID]:' . $order_id.'  Transaction rejected by system ');
						  $comment='Transaction rejected by system';
						  $Order_Status='8';
					  }
					  else
					  {
						   $this->logger->write('[Order ID]:' . $order_id.'  Transaction failed  ');
						  $comment='Transaction failed';
						  $Order_Status='10';
					  }
					  
					  $this->model_checkout_order->addOrderHistory($order_id, $Order_Status,$comment,true,false);
					  
					  if($Order_Status=='2')
					  {
						$this->session->data['ppc_Amount']=$this->request->post['ppc_Amount']; 
						$this->session->data['Order_No']= $order_id;
						$this->session->data['ppc_PinePGTransactionID']=$this->request->post['ppc_PinePGTransactionID']; 
						$this->session->data['ppc_Is_BrandEMITransaction']=$this->request->post['ppc_Is_BrandEMITransaction'];
						$this->session->data['ppc_IssuerName']=$this->request->post['ppc_IssuerName']; 
						$this->session->data['ppc_EMIInterestRatePercent']=$this->request->post['ppc_EMIInterestRatePercent'];	
						$this->session->data['ppc_EMIAmountPayableEachMonth']=$this->request->post['ppc_EMIAmountPayableEachMonth']; 
						$this->session->data['ppc_EMITotalDiscCashBackPercent']=$this->request->post['ppc_EMITotalDiscCashBackPercent'];
						$this->session->data['ppc_EMITotalDiscCashBackAmt']=$this->request->post['ppc_EMITotalDiscCashBackAmt'];
						$this->session->data['ppc_EMITenureMonth']=$this->request->post['ppc_EMITenureMonth'];
					    $this->session->data['ppc_EMICashBackType']=$this->request->post['ppc_EMICashBackType'];
						$this->session->data['ppc_EMIAdditionalCashBack']=$this->request->post['ppc_EMIAdditionalCashBack'];
						$this->session->data['ppc_UniqueMerchantTxnID']=$this->request->post['ppc_UniqueMerchantTxnID']; 
						 
						$this->response->redirect($this->url->link('extension/payment/pinepgsuccess', 'path=59'));
					  }
					  else if($Order_Status=='7')
					  {
					  	$this->response->redirect($this->url->link('extension/payment/pinepgcancelledtxn', 'path=59'));
					  }
					  else
					  {
							$this->response->redirect($this->url->link('checkout/failure', 'path=59'));
					  }
			}
			else
			{
				$this->logger->write('[Order ID]:' . $order_id.'  Transaction failed.Secure_Hash not matched with Pine PG Secure Hash ');
				$comment='Transaction failed.Secure_Hash not matched with Pine PG Secure Hash';
				 $Order_Status='10';
				$this->model_checkout_order->addOrderHistory($order_id, $Order_Status,$comment,true,false);
				$this->response->redirect($this->url->link('checkout/failure', 'path=59'));
			
			}
			
		}
		
	
	}
	else
	{ 		
			$this->logger->write('Post parameters received is empty');
			die('Illegal Access POST REQUEST IS EMPTY');
	}
	
   }
   
	 else 
	 {
		 $this->logger->write('[Order id]:' . $order_id.'  No order info exist ');
		 echo $order_id;
		 die('Illegal Access NO ORDER INFO EXIST');
     }
  }
   
  
}
?>