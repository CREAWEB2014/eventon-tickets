<?php
/**
 * Tickets addon functions for both front and backend
 * @version 1.6
 */
class evotx_bothends{
	public function __construct(){
		$this->eotx = get_option('evcal_options_evcal_tx');
		
		// send ticket email
		if(empty($this->eotx['evotx_tix_email']) || (!empty($this->eotx['evotx_tix_email']) && $this->eotx['evotx_tix_email']!='yes') ){
			add_action('woocommerce_order_status_completed', array($this, 'send_ticket_email'), 15, 1);	
		}
		
		// WC checkout order status changed		
		add_action('woocommerce_checkout_order_processed', array($this, 'create_evo_tickets'), 10, 1);
		add_action('woocommerce_checkout_order_processed', array($this, 'alter_event_orders'), 10, 1);
		add_action('woocommerce_reduce_order_stock', array($this, 'reduce_repeat_stock'), 10, 1);
		add_action('woocommerce_restore_order_stock', array($this, 'restore_repeat_stock'), 10, 1);

		// validation for stock availability
		add_action('woocommerce_check_cart_items', array($this, 'cart_validation'), 10);

		// when orders were cancelled, failed, or refunded
		// new order status to restock tickets
			foreach(array('cancelled','refunded') as $field){
				add_action('woocommerce_order_status_'.$field, array($this, 'restock_tickets'), 10,1);
			}

		// reduce stock actions
			foreach(array(
				array('old'=>'cancelled','new'=>'processing'),
			) as $status){
				add_action('woocommerce_order_status_'.$status['old'] .'_to_'. $status['new'], 
					array($this, 'reduce_tickets'), 10,1);
			}

		if( empty($this->eotx['evotx_hideadditional_guest_names']) || $this->eotx['evotx_hideadditional_guest_names'] !='yes' ):
			// show additional fields in checkout
				add_filter( 'woocommerce_checkout_fields', array($this,'filter_checkout_fields') );
				add_action( 'woocommerce_after_order_notes' ,array($this,'extra_checkout_fields') );
				add_action( 'woocommerce_after_checkout_validation' ,array($this,'extra_fields_process'), 10,2 );

			// save extra information
			add_action( 'woocommerce_checkout_update_order_meta', array($this,'save_extra_checkout_fields') );

			// display in wp-admin
			add_action( 'woocommerce_admin_order_data_after_order_details', array($this,'display_order_data_in_admin') );
			
			// display in order details section
			add_action( 'woocommerce_order_details_after_order_table', array($this,'display_orderdetails'),10,1 );
		endif;
	}


	// cart item validation
		function cart_validation(){
			global $evotx;
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

				// if event id and repeat interval missing skip those cart items
				if(empty($cart_item['evotx_event_id_wc'])) continue;
				if(empty($cart_item['evotx_repeat_interval_wc'])) continue;

				if ( $cart_item['product_id'] > 0 ) {
					$event_meta = get_post_custom($cart_item['evotx_event_id_wc']);
					$product_meta = get_post_custom($cart_item['product_id']);

					// if tickets disabled for events
					if(!evo_check_yn($event_meta, 'evotx_tix')){
						WC()->cart->remove_cart_item($cart_item_key);
						wc_add_notice( 'Item no longer for sale!' );
					}else{

						// check for stop selling tickets validation
						$stop_selling = $evotx->functions->stop_selling_now($event_meta, $cart_item['evotx_repeat_interval_wc']);

						$stock = $evotx->functions->event_has_tickets($event_meta, $product_meta, $cart_item['evotx_repeat_interval_wc']);

						// if there is no stocks or quantity is more than stock
						if(!$stock || $stop_selling){
							
							WC()->cart->remove_cart_item($cart_item_key);
							wc_add_notice( 'Item removed from cart, no longer available in stock!', 'error' );

						}elseif( $stock < $cart_item['quantity']){
							// if quantity is more than stock update quantity and refresh total
							WC()->cart->set_quantity($cart_item_key, $stock, true);
							wc_add_notice( 'Item quantity adjusted to stock levels!' );
						}
					}
					

					// action hook 
					do_action('evotix_cart_item_validation', $cart_item_key, $cart_item, $cart_item['evotx_event_id_wc'],$event_meta);
				}
				
			}
		}

	// Create tickets when an Order is processed - may not be completed yet
		function create_evo_tickets($order_id){
			global $evotx;
			$evotx->functions->create_tickets($order_id);
		}

		// restore the repeating events stock
		function restore_repeat_stock($order_id){
			$order = new WC_Order( $order_id );	

			if(sizeof( $order->get_items() ) <= 0) return false;
	    	
	    	global $evotx;

	    	// each order item in the order
	    	foreach ( $order->get_items() as $item) {

	    		if ( $item['product_id'] > 0 ) {
		    		
		    		$eid = get_post_meta( $item['product_id'], '_eventid', true);  

		    		if(empty($eid)) continue; // skip non ticket items
			
		    		// find repeat interval of the event item 
	    			$ri = $evotx->functions->get_ri_from_itemmeta($item);	   
	    			$evotx->functions->update_repeat_capacity($item['qty'], $ri, $eid );
			    }
	    	}
		}

		// reduce the repeating events stock
		function reduce_repeat_stock($order_id){
			$order = new WC_Order( $order_id );	

			if(sizeof( $order->get_items() ) <= 0) return false;
	    	
	    	global $evotx;

	    	// each order item in the order
	    	foreach ( $order->get_items() as $item) {

	    		if ( $item['product_id'] > 0 ) {
		    		
		    		$eid = get_post_meta( $item['product_id'], '_eventid', true);  

		    		if(empty($eid)) continue; // skip non ticket items
			
		    		// find repeat interval of the event item 
	    			$ri = $evotx->functions->get_ri_from_itemmeta($item);
	    			
    				// update the repeat stock, if keeping track of
    				$qty_adjust = (int)$item['qty']*-1;
    				$evotx->functions->update_repeat_capacity($qty_adjust, $ri, $eid );
			    }
	    	}
		}
	// alter event orders when checkout order is processed
		function alter_event_orders($order_id){
			global $evotx;			
			$evotx->functions->alt_initial_event_order($order_id);
		}

	// Additional guest names during checkout
		function filter_checkout_fields($fields){

		    $fields['evotx_field'] = array(
		            'evotx_field' => array(
		                'type' => 'text',
		                'required'      => false,
		                'label' => __( 'Event Ticket Data' )
		                ),
		            );
		    //print_r($fields);
		    return $fields;
		}
		function extra_checkout_fields(){ 

		    $checkout = WC()->checkout(); 

		    //print_r($checkout->checkout_fields['evotx_field']);

		    // fields required
		    	$required = evo_settings_check_yn($this->eotx, 'evotx_reqadditional_guest_names');	    
		   
		    // there will only be one item in this array - just to pass these values only for tx
		    foreach ( $checkout->checkout_fields['evotx_field'] as $key => $field ) : 
		    	
		    	global $woocommerce;
		    	$items = $woocommerce->cart->get_cart();

		    	$output = '';

		    	$datetime = new evo_datetime();


		    	// foreach item in the cart
		        foreach($items as $item => $values) { 

		        	$event_id = !empty($values['evotx_event_id_wc'])? $values['evotx_event_id_wc']:
		        		(!empty($values['evost_eventid'])? $values['evost_eventid']: false);

		        	if(!$event_id) continue;

		        	// get event time
			        	$RI = !empty($values['evotx_repeat_interval_wc'])? (int)$values['evotx_repeat_interval_wc']:0;
			        	$event_times = $datetime->get_correct_event_time($event_id, $RI);
			        	$event_time = $datetime->get_formatted_smart_time($event_times['start'], $event_times['end'],'',$event_id);

		        	$_product = wc_get_product($values['variation_id'] ? $values['variation_id'] : $values['product_id']);
 
		        	$product_id = $_product->get_id();

		        	// if there are variation
		        		$variation_text = '';
		        		if(!empty($values['variation'])){
		        			//print_r($values);
		        			foreach($values['variation'] as $key=>$value){
		        				$field = str_replace('attribute_','',$key);
		        				$field = str_replace('pa_', '', $field);

		        				$value = str_replace('-', ' ', $value);
		        				$variation_text .= "<span>".$field. ': '.$value."</span> ";
		        			}
		        			$variation_text = "<br/>".$variation_text;
		        		}

		        	$output.= "<p>";
		        	$output .= "<span style='display:block'><b>". evo_lang_get('evoTX_005d','Event Name').':</b> '. get_the_title($event_id) . $variation_text."</span>";
		        	$output .= "<span style='display:block'><b>". evo_lang_get('evoTX_005a','Event Time').':</b> '. $event_time."</span>";
		        	$output .= "</p>";

		        	if($values['quantity']>0){
		        		for($x=0; $x<$values['quantity']; $x++){

		        			$result = woocommerce_form_field('tixholders['.$event_id.'][]', array(
								'type' => 'text',
								'class' => array('my-field-class form-row') ,
								'label' => apply_filters('evotx_checkout_addnames_label',evo_lang('Full Name of the Ticket Holder'),$item, $values, $event_id) ." #".($x+1),
								'placeholder' => evo_lang('Full Name') ,
								'required' => $required,
								'return'=>true
							) , $checkout->get_value('tixholders['.$event_id.'][]'));

							$output .= apply_filters('evotx_checkout_fields', $result, $event_id, $x );
		        		}
		        	}        
		        } 

		        echo !empty($output)? "<div class='extra-fields'>
		        	<div class='evotx_checkout_additional_names'>
		        	<h3>".evo_lang( 'Additional Ticket Information' )."</h3>".$output . 
		        	'</div></div>':'';

		    endforeach; ?>	   

		<?php }

		function extra_fields_process( $data, $errors ){
			//print_r($data);
			if(!empty($_POST['tixholders'])){
				$required = evo_settings_check_yn($this->eotx, 'evotx_reqadditional_guest_names');	

				//print_r($_POST['tixholders']);

				if($required){
					$empty = false;
					foreach($_POST['tixholders'] as $event=>$names){
						if($empty) continue;						
						//$names = array_filter($names);

						// each name for the event
						foreach($names as $name){
							if( empty($name) ) $empty = true;
						}
					}

					if($empty){ 
						wc_add_notice(  sprintf( _x( '%s is a required field.', 'FIELDNAME is a required field.', 'evotx' ), '<strong>Additional Ticket Information</strong>' ), 'error' );
					}
				}
			}
			
		}

		function save_extra_checkout_fields( $order_id ){
			if( !empty( $_POST['tixholders'] ) ) {
		    	update_post_meta( $order_id, '_tixholders',  $_POST['tixholders']  );
		    	do_action('evotx_checkout_fields_saving', $order_id);
		    }
		}
		function display_order_data_in_admin( $order ){

			$tixHolders = get_post_meta( $order->get_id(), '_tixholders', true );
			if(empty($tixHolders)) return $order;
		?>
		    <div class="order_data_column">
		        <h4><?php _e( 'Ticket Holder Details', 'evotx' ); ?></h4>
		        <?php 
		        	if(!empty($tixHolders) && is_array($tixHolders)){
		        		foreach($tixHolders as $eventid => $names_array){
		        			array_filter($names_array,'strlen');
		        			//print_r($names_array);
		        			echo "<p>";
		        			
		        			if(sizeof($names_array)>0){
		        				echo implode(', ', $names_array);		        				
		        			}else{
		        				echo __('Additional ticket holder names not specified!','evotx');
		        			}
		        			
		        			echo "</p>";
		        		}
		        	}

		        do_action('evotx_checkout_fields_display_admin', $order);
		        ?>
		    </div>
		<?php 
		}

		function display_orderdetails($order){
			$tixHolders = get_post_meta( $order->get_id(), '_tixholders', true );
			if(empty($tixHolders)) return $order;

			if(!empty($tixHolders) && is_array($tixHolders)):
			?>
				<header><h2><?php _e( 'Ticket Holder Details', 'evotx' ); ?></h2></header>
				<table class="shop_table ticketholder_details">
					<?php 
					foreach($tixHolders as $eventid=>$names):?>
						<tr>
							<th><?php _e( 'Names:', 'evotx' ); ?></th>
							<td><?php echo implode(', ', $names); ?></td>
						</tr>
					<?php endforeach;?>
				</table>
			<?php 
			endif;

			do_action('evotx_checkout_fields_display_orderdetails', $order);
		}

	// Auto re-stock and reduce tickets
		function restock_tickets($orderid){
			global $evotx;
			$evotx->functions->restock_tickets($orderid);
		}
		function reduce_tickets($orderid){
			global $evotx;
			$evotx->functions->reduce_stock($orderid);
		}


	// EMAILING
		function send_ticket_email($order_id){
			$email = new evotx_email();
			// initial ticket email
			$email->send_ticket_email($order_id, false, true);
		}
}