<?php
/**
 * EventON Ticket corresponding to WC order
 * @version 0.1
 */

class evotx_tix{

// Tickets based on WC Order
	function get_event_id_by_product_id($product_id){
		$event_id = get_post_meta($product_id, '_eventid',true);
		if(empty($event_id)){
			$product_id = wp_get_post_parent_id($product_id);
			$event_id = get_post_meta($product_id, '_eventid',true);

			return ($event_id)? $event_id: false;
		}
		return ($event_id)? $event_id: false;
	}
	function get_ticket_variation_id($ticket_number){
		$tt = explode('-', $ticket_number);

		$product_id = wp_get_post_parent_id( (int)$tt[2]);
		return ( !$product_id)? false: (int)$tt[2];

	}

	function get_ticket_numbers_for_order($order_id){
		$ticket_ids = get_post_meta($order_id, '_tixids', true);
		return $ticket_ids? $ticket_ids: false;
	}

	function get_evotix_id_by_product_order($order_id, $product_id, $complete=false){
		$ticket_ids = get_post_meta($order_id, '_tixids', true); // returns Array ( [0] => 1837-1836-1831 [1] => 1838-1836-1830 )

		if(empty($ticket_ids)) return false;

		//print_r($ticket_ids);

		foreach($ticket_ids as $ticket_number){
			$tt = explode('-', $ticket_number);

			if($tt[1]==$order_id &&  $tt[2]==$product_id){
				return $complete? $ticket_number : $tt[0];
			}
		}
		return false;
	}
	function get_ticket_number_by_productorder($order_id, $product_id){
		return $this->get_evotix_id_by_product_order($order_id, $product_id, true);
	}
	function get_product_id_by_ticketnumber($ticket_number){
		$tt = explode('-', $ticket_number);
		return (int)$tt[2];
	}
	function get_evotix_id_by_ticketnumber($ticket_number){
		$tt = explode('-', $ticket_number);
		return (int)$tt[0];
	}

	function get_order_ticket_holders($order_id){
		$order_ticket_holders = get_post_meta($order_id, '_tixholders', true);
		return ($order_ticket_holders)? $order_ticket_holders: false;
		// returns array(event_id=> array(names))
	}
	function get_ticket_holders_forevent($event_id, $ticket_holders_array){
		if(!is_array($ticket_holders_array)) return false;

		if(!isset($ticket_holders_array[$event_id])) return false;

		return array_filter($ticket_holders_array[$event_id]);
	}
	function get_ticket_purchaser_info($ticket_number){
		$tt = explode('-', $ticket_number);
		$evotix_meta = get_post_custom($tt[0]);

		return (!empty($evotix_meta['name'])? $evotix_meta['name'][0]:'').' '.
			(!empty($evotix_meta['email'])? $evotix_meta['email'][0]:'');
	}
	function get_ticket_holder_by_ticket_number($ticket_number, $order_id=''){

		if(empty($order_id)){
			$tt = explode('-', $ticket_number);
			$order_id = $tt[1];
		}

		$order_ticket_number = $this->get_ticket_numbers_for_order($order_id);

		if(empty($order_ticket_number)) return false;

		$all_ticket_holders = $this->get_order_ticket_holders($order_id);

		if(empty($all_ticket_holders)) return false;

		$ticket_holders = array();
		foreach($all_ticket_holders as $event=>$holders){
			foreach($holders as $holder){
				$ticket_holders[] = $holder;
			}
		}

		$numberindex = array_search($ticket_number, $order_ticket_number);

		if(isset($ticket_holders[$numberindex])){
			return $ticket_holders[$numberindex];
		}

		return false;
	}


// Ticket Quantity related
	function fix_incorrect_qty($evotix_id){
		$ticket_ids = get_post_meta($evotix_id, 'ticket_ids',true);
		if($ticket_ids){
			$qty = count($ticket_ids);
			update_post_meta($evotix_id, 'qty', $qty);
		}
	}

// Ticket status related
	function get_ticket_numbers_by_evotix($evotix_id, $return_type = 'array'){
		$output = '';
		$ticket_ids = get_post_meta($evotix_id, 'ticket_ids',true);

		if($ticket_ids){
			$output = $ticket_ids;
		}else{// if ticket IDs were saved on older method
			$tids = get_post_meta($evotix_id, 'tid',true);

			if(empty($tid)) return false;

			$tids =   explode(',',$tids);
			$data = array();
			foreach($tids as $ids){
				$data[$ids] = 'check-in'; 
			}

			update_post_meta($evotix_id, 'ticket_ids',$data);
			$output =  $data;
		}

		if($return_type=='array'){
			return $output;
		}else{
			// comma separated string
			$str = ''; $index = 1;
			foreach($output as $key=>$val){
				$str .= ($index== count($output)? $key: $key.', ');
				$index++;
			}
			return $str;
		}

	}

	function get_checkin_status_text($status, $lang=''){
		global $evotx;
		$evopt = $evotx->opt2;
		$lang = (!empty($lang))? $lang : 'L1';

		if($status=='check-in'){
			return (!empty($evopt[$lang]['evoTX_003x']))? $evopt[$lang]['evoTX_003x']: 'check-in';
		}else{
			return (!empty($evopt[$lang]['evoTX_003y']))? $evopt[$lang]['evoTX_003y']: 'checked';
		}
	}
	function get_other_status($status=''){
		$new_status = ($status=='check-in')? 'checked':'check-in';
		$new_status_lang = $this->get_checkin_status_text($new_status);

		return array($new_status, $new_status_lang);
	}
	function checked_count($evotix_id){
		$status = get_post_meta($evotix_id, 'status',true);
		$qty = get_post_meta($evotix_id, 'qty',true);
		$ticket_ids = get_post_meta($evotix_id, 'ticket_ids',true);

		if($ticket_ids){
			$count = array_count_values($ticket_ids);
			$count['checked'] = ( !empty($count['checked'] )? $count['checked'] : 0);
			$count['qty'] = !empty($qty)? $qty:1;
			return $count; // Array ( [check-in] => 2 )
		}else{
			$status =  (!empty($status))? $status: 'check-in';
			return array($status=>'1', 'qty'=>(!empty($qty)? $qty:1) );
		}
	}
	function get_ticket_status_by_ticket_number($ticket_number){
		$tixNum = explode('-', $ticket_number);
		$evotix_id = $tixNum[0];

		$ticket_ids = get_post_meta($evotix_id, 'ticket_ids',true);

		if(!empty($ticket_ids) ){
			if(array_key_exists($ticket_number, $ticket_ids)){
				return $ticket_ids[$ticket_number];
			}else{
				return 'check-in';
			}
		}else{
			$status = get_post_meta($evotix_id, 'status',true);
			return (!empty($status))? $status: 'check-in';
		}
	}
	function change_ticket_number_status($new_status, $ticket_number, $evotix_id){
		$ticket_ids = get_post_meta($evotix_id, 'ticket_ids',true);
		if($ticket_ids){
			unset($ticket_ids[$ticket_number]);
			
			$ticket_ids[$ticket_number]= $new_status;
			update_post_meta($evotix_id, 'ticket_ids',$ticket_ids);					
		}else{
			update_post_meta($evotix_id, 'status',$new_status);						
		}
	}
	// return ticket numbers if there are other tickets in the same order
	function get_other_tix_order($ticket_number){
		$tixNum = explode('-', $ticket_number);
		$evotix_id = $tixNum[0];
		$ticket_ids = get_post_meta($evotix_id, 'ticket_ids',true);

		unset($ticket_ids[$ticket_number]);

		return $ticket_ids;
	}
	

}