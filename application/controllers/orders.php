<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Orders extends CI_Controller {

	public function __construct(){ 
		parent::__construct();
		$this->load->model('orders_model');
		$this->load->model('items_model');
		$this->load->model('users_model');
		$this->load->model('currency_model');
		$this->load->library('my_session');
	}

	// URI: orders
	public function index(){
		$this->load->library('form_validation');
		$data['title'] = 'Orders';
		$data['page'] = 'orders/index';
		$data['orders'] = $this->orders_model->myOrders();
		$this->load->library('layout',$data);
	}
	
	public function recount(){
		$this->load->library('form_validation');
		$list = $this->input->post('quantity');
		//print_r($list);
		$buyerHash = $this->my_session->userdata('userHash');
		
		$orders = array();
		$sellerCount = 0;
		$sellerKeys = array_keys($list);
		$itemCount = 0;
		
		$count = 0;
		foreach($list as $byseller){
			$count = 0;
			$keys = array_keys($byseller);
			foreach($byseller as $quantity){
			
				$itemHash = $keys[$count];
				$itemInfo = $this->items_model->getInfo($itemHash);
				
				$currentOrder = $this->orders_model->check($buyerHash,$itemInfo['sellerID']);

				$updateOrder = array(	'id' => $currentOrder[0]['id'],
							'itemHash' => $itemInfo['itemHash'],
							'quantity' => $quantity);
				if($this->orders_model->updateOrder($updateOrder) === TRUE){
					$data['returnMessage'] = "Your order has been updated.";
				} else {
					$data['returnMessage'] = 'Unable to update your order.';
				}
				$count++;
			}
			
		}
		$data['title'] = 'Orders';
		$data['page'] = 'orders/index';
		$data['orders'] = $this->orders_model->myOrders();
		$this->load->library('layout',$data);
	}
	
	// URI: order/
	public function orderItem($itemHash){
		$this->load->library('form_validation');
		$itemInfo = $this->items_model->getInfo($itemHash);
		if($itemInfo === NULL){
			// Item not fond
			$data['title'] = 'Not Found';
			$data['page'] = 'orders/index';
			$data['returnMessage'] = 'That item was not found.';
			$data['orders'] = $this->orders_model->myOrders();
		} else {
			$userInfo = $this->users_model->get_user(array('userHash' => $this->my_session->userdata('userHash')));
			
			$currentOrder = $this->orders_model->check($userInfo['userHash'],$itemInfo['sellerID']);
			if($currentOrder === NULL){
				// No current order to that seller
				$placeOrder = array(	'buyerHash' => $userInfo['userHash'],
							'sellerHash' => $itemInfo['sellerID'],
							'items' => $itemHash."-1",
							'totalPrice' => $itemInfo['price'],
							'currency' => $itemInfo['currency'],
							'time' => time() );

				if($this->orders_model->createOrder($placeOrder)){
					// Order placed.
					$data['title'] = 'Order Placed';
					$data['returnMessage'] = 'Your order has been created.';
			
				} else {
					// Unable to make this order!
					$data['title'] = 'Orders';
					$data['returnMessage'] = 'Unable to add this item to your order, please try again.';
				}
			} else {
				// There is currently an order to that Vendor.
				if($currentOrder[0]['step'] == '0'){
				
					$currentQuantity = $this->orders_model->getQuantity($itemHash);

					$updateOrder = array(	'id' => $currentOrder[0]['id'],
								'itemHash' => $itemHash,
								'quantity' =>  $currentQuantity+1);

					if($this->orders_model->updateOrder($updateOrder)){
						// Order updated with new information
						$data['title'] = 'Item Added';
						$data['returnMessage'] = 'The item has been added to your order.';

					} else {
						$data['title'] = 'Orders';
						$data['returnMessage'] = 'Unable to add this item to your order, please try again.';

					}
				} else {
					$data['title'] = 'Order already placed';
					$data['returnMessage'] = 'This order has already been placed. Please contact your vendor to discuss any further changes.';

				}
			}
		}
		$data['page'] = 'orders/index';
		$data['orders'] = $this->orders_model->myOrders();	
		$this->load->library('layout',$data);
	}

	// URI: order/place/
	public function place($sellerHash){
		$this->load->library('form_validation');
		$this->load->model('messages_model');
		$currentUser = $this->my_session->userdata('userHash');
		$currentOrder = $this->orders_model->check($currentUser,$sellerHash);

		if($currentOrder === NULL){
			// Order placed.
			$data['title'] = 'Error';
			$data['returnMessage'] = 'You currently have no orders for this user.';
		} else {
			if($currentOrder[0]['step'] == "0"){
				if($this->orders_model->nextStep($currentOrder[0]['id'],'0') === TRUE){

					// Send the seller a message about the order
					$messageText = "You have received a new order from ".$currentOrder[0]['buyer']['userName']."\n\n";
					for($i = 0; $i < count($currentOrder[0]['items']); $i++){
						$messageText.= "{$currentOrder[0]['items'][$i]['quantity']} x {$currentOrder[0]['items'][$i]['name']}\n";
					}

					$messageText .= "Total price: {$currentOrder[0]['currencySymbol']}{$currentOrder[0]['totalPrice']}";

					$messageHash = $this->general->uniqueHash('messages','messageHash');
					$threadHash = $this->general->uniqueHash('messages','threadHash');

					$messageArray = array(  'toId' => $currentOrder[0]['seller']['id'],
							        'fromId' => $currentOrder[0]['buyer']['id'],
							        'messageHash' => $messageHash,
								'orderID' => $currentOrder[0]['id'],
								'subject' => "New Order from ".$currentOrder[0]['buyer']['userName'],
								'message' => nl2br($messageText),
								'encrypted' => '0',
								'time' => time(),
								'threadHash' => $threadHash
					);

					$data['title'] = 'Order Placed';
					$data['returnMessage'] = 'Your order has been placed. Please authorize payment to this sellers account to continue.';

					if($this->messages_model->addMessage($messageArray) !== TRUE){
						$data['returnMessage'] = "Unable to send a message to {$currentOrder[0]['buyer']['userName']}";
					}

				} else {
					$data['title'] = 'Error';
					$data['returnMessage'] = 'Unable to progress this order, please try again later.';
				}
			} else {
				$data['title'] = 'Error';
				$data['returnMessage'] = 'This order has already been placed.';
			}	
		}
				$data['page'] = 'orders/index';
				$data['orders'] = $this->orders_model->myOrders();
		$this->load->library('layout',$data);
	}

	public function review(){
		$data['title'] = 'Soon to come..';
		$data['page'] = 'orders/review';
		$data['returnMessage'] = 'This content will come soon..';
		$this->load->library('layout',$data);
	}


};

