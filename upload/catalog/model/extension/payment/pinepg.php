<?php
class ModelExtensionPaymentPinePG extends Model {
  public function getMethod($address, $total) {
    $this->load->language('extension/payment/pinepg');
  
    $method_data = array(
      'code'     => 'pinepg',
      'title'    => $this->language->get('text_title'),
      'sort_order' => $this->config->get('custom_sort_order'),
	  'terms'=>''
    );
  
    return $method_data;
  }
}