<?php
/**
 * 
 *  EventON Ticket Order Item class
 *	User Order Item data to create corresponding ticket item data
 *
 * @author 		AJDE
 * @category 	Admin
 * @package 	eventon-tickets/Classes
 * @version     1.2.1
 */

class evotx_ticket_orderitem{

	public $order_item;
	public $order_id;
	public $ticket_item_meta;
	public $the_ticket_item_id;
	
	/*
		order item - array of order item meta
	*/
	function __construct($order_item, $order_id, $ticket_item_meta=''){
		$this->order_item = $order_item;
		$this->order_id = $order_id;
		$this->ticket_item_meta = $ticket_item_meta;

		$this->the_ticket_item_id = $this->ticket_item_id();
	}

	// generate ticket item id 
	function ticket_item_id(){
		$product_id = ( !empty($this->order_item['variation_id'])? $this->order_item['variation_id']: $this->order_item['product_id']);

		// get ticket IDS saved in order
			$ticket_ids = get_post_meta($this->order_id, '_tixids', true);
			$_code_mid = $this->order_id.'-'.$product_id;

			$ticket_item_id='';
			
			if(!is_array($ticket_ids)) return $ticket_item_id;

			//find ticket item id from saved ticket item ids in order meta
			foreach($ticket_ids as $__tid){
				$__tid_1 = explode(',', $__tid);
				
				if(strpos($__tid_1[0], $_code_mid)){
					$tt = explode('-', $__tid_1[0]);					
					$ticket_item_id = $tt[0];
				}
			}	
			return $ticket_item_id;
	}

	function event_id(){
		return get_post_meta($this->order_item['product_id'], '_eventid', true);
	}


	function ticket_ids( $type='array', $ticket_item_meta =''){
		// initials
		$ticket_item_meta = !empty($ticket_item_meta)? $ticket_item_meta: 
			(!empty($this->ticket_item_meta)? $this->ticket_item_meta: get_post_custom($this->the_ticket_item_id ) );

		$output = '';
		if(!empty($ticket_item_meta['ticket_ids']) ){

			$ticket_ids_arry = unserialize($ticket_item_meta['ticket_ids'][0]);

			if($type=='array'){
				return $ticket_ids_arry;
			}else{ // comma string
				foreach( $ticket_ids_arry as $ff=>$vv){
					if(count($ticket_ids_arry)>0){
						$output.= $ff.', ';
					}else{	$output.= $ff;	}
				}
				return $output;
			}
			
		}else{ // ticket id does not exist
			return  !empty($ticket_item_meta['tid'])? $ticket_item_meta['tid'][0]: false;
		}
	} // this functino is duplicate from ticket item class
	
	function repeat_interval(){
		// initials
		$ticket_item_meta = !empty($ticket_item_meta)? $ticket_item_meta: 
			(!empty($this->ticket_item_meta)? $this->ticket_item_meta: get_post_custom($this->the_ticket_item_id ) );
		return (!empty($ticket_item_meta['repeat_interval'])? $ticket_item_meta['repeat_interval'][0]: 0);
	}

}
