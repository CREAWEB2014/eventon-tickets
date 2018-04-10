<?php
/**
 * eventon tickets front end class
 *
 * @author 		AJDE
 * @category 	Admin
 * @package 	eventon-tickets/Classes
 * @version     1.5.6
 */

class evotx_front{

	function __construct(){
		global $evotx;
		// event top inclusion
		//add_filter('eventon_eventtop_one', array($this, 'eventop'), 10, 3);
		//add_filter('evo_eventtop_adds', array($this, 'eventtop_adds'), 10, 1);
		//add_filter('eventon_eventtop_evotx', array($this, 'eventtop_content'), 10, 2);
		
		$this->opt1 = get_option('evcal_options_evcal_1');
		$this->opt2 = get_option('evcal_options_evcal_2');
		$this->eotx = get_option('evcal_options_evcal_tx');
		
		// event card inclusion
		add_filter('eventon_eventCard_evotx', array($this, 'frontend_box'), 10, 2);
		add_filter('eventon_eventcard_array', array($this, 'eventcard_array'), 10, 4);
		add_filter('evo_eventcard_adds', array($this, 'eventcard_adds'), 10, 1);

			// event top above title
				add_filter('eventon_eventtop_abovetitle', array($this,'eventtop_above_title'),10, 2);
		
		// scripts and styles 
		add_action( 'init', array( $this, 'register_styles_scripts' ) ,15);	
		add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ), 10 );


		// thank you page tickets
		if( !evo_settings_val('evotx_hide_thankyou_page_ticket',$this->eotx) )
			add_action('woocommerce_thankyou', array( $this, 'wc_order_tix' ), 10 ,1);

		if( !evo_settings_check_yn($this->eotx,'evotx_hide_orderpage_ticket')){
			add_action('woocommerce_view_order', array( $this, 'wc_order_tix' ), 10 ,1);
		}
		
		// EMAILS
		// order item name in emails
		add_filter('woocommerce_order_item_name', array($this, 'order_item_name'), 10, 2);
		add_filter('woocommerce_display_item_meta', array($this, 'order_item_meta'), 10, 3);
		add_filter('woocommerce_email_order_meta_fields', array($this, 'order_item_meta_alt'), 10, 3);


		// Passing Repeat interval related actions		
		add_filter('woocommerce_add_cart_item_data',array($this,'add_item_data'),1,2);
		add_filter('woocommerce_get_cart_item_from_session', array($this,'wdm_get_cart_items_from_session'), 1, 3 );

		// display custom date in cart
		add_filter('woocommerce_cart_item_name',array($this,'cart_item_name_box'),1,3);
		add_filter('woocommerce_cart_item_permalink',array($this,'cart_item_permalink'),1,3);

		// display order details
		add_filter('woocommerce_order_items_meta_display', array($this, 'ordermeta_display'), 10,2);

		// saving meta data
		add_action('woocommerce_checkout_create_order_line_item',array($this,'order_item_meta_update_new'),1,4);
		add_action('woocommerce_before_cart_item_quantity_zero',array($this,'wdm_remove_user_custom_data_options_from_cart'),1,1);

		add_action('evo_addon_styles', array($this, 'styles') );

		// quantity in cart
		add_filter('woocommerce_cart_item_quantity',array($this,'cart_item_quantity'),1,3);

		// front-end template redirect
		add_action('template_redirect', array($this, 'template_redirect'), 10, 1);

	}

	// template redirect
		function template_redirect(){
			if( !evo_settings_check_yn($this->eotx,'evotx_wc_prod_redirect')) return false;

			if(is_product()) {
				$event_id = get_post_meta(get_queried_object_id(), '_eventid',true);
				if($event_id) {
					$event_url = get_permalink($event_id);
					if($event_url !== false) {
						wp_redirect($event_url);
						exit();
					}
				}
			}
		}

	// dynamic changes to cart quantity field
		function cart_item_quantity($product_quantity, $cart_item_key, $cart_item='' ){
			//print_r($cart_item);
			if(empty($cart_item)) return $product_quantity;
	   		if(empty($cart_item['evotx_event_id_wc']) ) return $product_quantity;
	   		if(empty($cart_item['evotx_repeat_interval_wc']) ) return $product_quantity;
   		

	   		$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

	   		$max_qty = $_product->backorders_allowed() ? '' : $_product->get_stock_quantity();

	   		if( $_product && $_product->is_type('simple')){

	   			$event_pmv = get_post_meta($cart_item['evotx_event_id_wc']);
	   			$product_pmv = get_post_meta($_product->get_id());

	   			global $evotx;
	   			$tix_inStock = $evotx->functions->event_has_tickets($event_pmv, $product_pmv, $cart_item['evotx_repeat_interval_wc']);

	   			$max_qty = $tix_inStock;
	   		}

	   		$product_quantity =woocommerce_quantity_input( array(
				'input_name'  => "cart[{$cart_item_key}][qty]",
				'input_value' => $cart_item['quantity'],
				'max_value'   => $max_qty,
				'min_value'   => '0',
			), $_product, false );
	   		return $product_quantity;
	   		
	   	}

	// Order details meta display
		function ordermeta_display($output, $obj){
			$output = str_replace('Event Time', $this->langX('Event Time','evoTX_005a'), $output);
			$output = str_replace('Event Location', $this->langX('Event Location','evoTX_005c'), $output);
			return $output;
		}

	// Event TOP inclusion
		public function eventop($array, $pmv, $vals){
			$array['evotx'] = array(
				'vals'=>$vals,
			);
			return $array;
		}
		public function eventtop_content($object, $helpers){
			$output = '';
			$emeta = get_post_custom($object->vals['eventid']);


			// if tickets and enabled for the event
			if( !empty($emeta['evotx_tix']) && $emeta['evotx_tix'][0]=='yes'
				&& $object->vals['fields_'] && in_array('organizer',$object->vals['fields'])
			){

				global $product;
				$woo_product_id = $emeta['tx_woocommerce_product_id'][0];
				$product = wc_get_product($woo_product_id);

				if(!$product->is_type( 'simple' ) ) return $output;
						
				$output .= "<span class='evotx_add_to_cart' data-product_id='{$woo_product_id}' data-event_id='{$object->vals['eventid']}' data-ri='{$object->vals['ri']}'><em>Add to cart</em></span>";
			}	

			return $output;
		}
		// event card inclusion functions		
			function eventtop_adds($array){
				$array[] = 'evotx';
				return $array;
			}

		// above title - sold out tag
			function eventtop_above_title($var, $object){
				$epmv = $object->evvals;

				// dismiss if set in ticket settings not to show sold out tag on eventtop
				if(evo_settings_check_yn($this->eotx, 'evotx_eventop_soldout_hide')) return $var;

				// event have tickets enabled
				if(!empty($epmv['evotx_tix']) && $epmv['evotx_tix'][0]=='yes' && !empty($epmv['tx_woocommerce_product_id'])){
					global $evotx;
					$woometa = get_post_custom($epmv['tx_woocommerce_product_id'][0]);

					$haveTix = $evotx->functions->event_has_tickets($epmv, $woometa, $object->ri);
					if(!$haveTix){
						return "<span class='evo_soldout'>".$this->langX('Sold Out!', 'evoTX_012')."</span>";
					}else{
						// check with settings if event over to be hidden
						if(evo_settings_check_yn($this->eotx, 'evotx_eventop_eventover_hide')) return $var;

						$isCurrentEvent = $evotx->functions->is_currentEvent($epmv, $object->ri);

						if( !$isCurrentEvent)
							return "<span class='eventover'>".$this->langX('Event Over', 'evoTX_012b')."</span>";
						
					}
				}
			}

	// passing on Repeat interval for event to order		
		// pass RI session to WC session
			function add_item_data($cart_item_data,$product_id){
		        /*Here, We are adding item in WooCommerce session with, evotx_repeat_interval_wc name*/
		       	//session_start();   
		       	//update_option('aaa',$_SESSION);		        
		        //print_r($_REQUEST);
		        //echo $_REQUEST['add-to-cart'].' '.$_REQUEST['ri'].' '.$_REQUEST['eid'].' '.$product_id;
		        
		        if( !empty($_REQUEST['add-to-cart']) &&	$_REQUEST['add-to-cart'] == $product_id && 
		        	isset($_REQUEST['ri']) &&
		        	!empty($_REQUEST['eid'])
		        ){
		        	$new_value = array();
		        	
		        	if(!isset($cart_item_data['evotx_repeat_interval_wc']))
		        		$new_value['evotx_repeat_interval_wc'] = (!empty($_REQUEST['ri'])? $_REQUEST['ri']:0);
		        	
		        	$new_value['evotx_event_id_wc'] = $_REQUEST['eid'];

		        	if(!empty($_REQUEST['eloc'])) $new_value['evotx_elocation'] = urldecode($_REQUEST['eloc']);

		        	return (empty($cart_item_data))? $new_value: array_merge($cart_item_data,$new_value);

		        }else{
		        	return $cart_item_data;
		        }
		    }
		// insert into cart object
		    function wdm_get_cart_items_from_session($item, $values, $key){
		    	    	
		        // updates values
		        if (array_key_exists( 'evotx_repeat_interval_wc', $values ) ){
		       		$item['evotx_repeat_interval_wc'] = $values['evotx_repeat_interval_wc'];
		        } 
		        if (array_key_exists( 'evotx_event_id_wc', $values ) ){
		       		$item['evotx_event_id_wc'] = $values['evotx_event_id_wc'];		       		
		        } 
		        if (array_key_exists( 'evotx_elocation', $values ) ){
		       		$item['evotx_elocation'] = $values['evotx_elocation'];		       		
		        }  

		        return $item;
		    }

	// CART TICKET DIDPLAY
		// cart ticekt permalink alteration
			function cart_item_permalink($link, $cart_item, $cart_item_key){
				if(empty($cart_item['evotx_event_id_wc'])) return $link;

				return get_permalink($cart_item['evotx_event_id_wc']);
			}

		// display custom data in the cart
		    function cart_item_name_box($product_name, $values, $cart_item_key ) {
		    	global $evotx;
		    	//print_r($values);
		    	/*code to add custom data on Cart & checkout Page*/    
		        if(isset($values['evotx_repeat_interval_wc']) 	&& count($values['evotx_repeat_interval_wc']) > 0
		        ){
		        	$ri = (!empty($values['evotx_repeat_interval_wc']))? $values['evotx_repeat_interval_wc']: 0;

		        	// get the correct event time
		        	$ticket_time = $evotx->functions->get_event_time('', $ri, $values['evotx_event_id_wc']);
		        			        	
		            $return_string = $product_name;
		            $return_string .= "<br/><span class='item_meta_data'>";
		            $return_string .= '<span class="item_meta_data_eventtime"><b>'. $this->langX('Event Time','evoTX_005a')."</b>: " . $ticket_time . "</span>";

		            
		            // event location data
		            if(isset($values['evotx_elocation'])){
		            	$return_string .=  "<br/><span class='item_meta_data_eventtime'><b>".$this->langX('Event Location','evoTX_005c') ."</b>: " . 
		            		stripslashes($values['evotx_elocation']) . '</span>';
		            }
		            		            
		            $return_string .= "</span>";  
		            
		            return $return_string;
		        }else if( !empty($values['evotx_event_id_wc']) ){
		        	return $product_name; 
		        }else{    
		        	return $product_name;    
		        }
		    }


		// add custom data as meta data to order item
		    function order_item_meta_update_new($item, $cart_item_key, $values, $order){
		        global $evotx;

		        // if event id and repeat interval saved in session
		        if(isset($values['evotx_repeat_interval_wc']) && !empty($values['evotx_event_id_wc'])){
			        
			        $ri = (!empty($values['evotx_repeat_interval_wc']))? $values['evotx_repeat_interval_wc']: 0;
			        
			        $time = $evotx->functions->get_event_time('', $ri, $values['evotx_event_id_wc']);
			        $ticket_time = $time;			        
			        $ticket_time_add = ($ri!= 0)? ' [RI'.$ri.']':''; // append RI value to event time data

			        $item->add_meta_data( 'Event-Time' , $ticket_time.$ticket_time_add , true); 
		        }

		        // event location
		        if(isset($values['evotx_elocation']) ){
		        	$item->add_meta_data( 'Event-Location' , $values['evotx_elocation'] , true); 
		        }		      
			}
		// remove custom data if item removed from cart
			function wdm_remove_user_custom_data_options_from_cart($cart_item_key){
		        global $woocommerce;
		        // Get cart
		        $cart = $woocommerce->cart->get_cart();
		        
		        // For each item in cart, if item is upsell of deleted product, delete it
		            foreach( $cart as $key => $values){
			        	if(empty($values['evotx_repeat_interval_wc']) ) continue;

				        if ( $values['evotx_repeat_interval_wc'] == $cart_item_key ){
				            unset( $woocommerce->cart->cart_contents[ $key ] );
				        }
				        if( $values['evotx_elocation'] == $cart_item_key )
				        	unset( $woocommerce->cart->cart_contents[ $key ] );
			        }
		    }

	// show tickets in front-end customer account pages
	// Only when order is completed
		public function wc_order_tix($order_id){
			
			$order = new WC_Order( $order_id );

			if(EVOTX()->functions->does_order_have_tickets($order_id)){

				// completed orders
				if ( in_array( $order->get_status(), array( 'completed' ) ) ) {

					$evotx_tix = new evotx_tix();
					
					$customer = get_post_meta($order_id, '_customer_user');
					$userdata = get_userdata($customer[0]);

					$order_tickets = $evotx_tix->get_ticket_numbers_for_order($order_id);
					
					$email_body_arguments = array(
						'orderid'=>$order_id,
						'tickets'=>$order_tickets, 
						'customer'=>(isset($userdata->first_name)? $userdata->first_name:'').
							(isset($userdata->last_name)? ' '.$userdata->last_name:'').
							(isset($userdata->user_email)? ' '.$userdata->user_email:''),
						'email'=>''
					);

					$wrapper = "-webkit-text-size-adjust:none !important;margin:0;";
					$innner = "-webkit-text-size-adjust:none !important; margin:0;";
					
					ob_start();
					?>
					<h2><?php echo evo_lang_get('evoTX_014','Your event Tickets','',$this->opt2);?></h2>
					<div class='evotx_event_tickets_section' style="<?php echo $wrapper; ?>">
					<div class='evotx_event_tickets_section_in' style='<?php echo $innner;?>'>
					<?php
						$email = new evotx_email();
						echo $email->get_ticket_email_body_only($email_body_arguments);

					echo "</div></div>";

					echo ob_get_clean();
						
				}else{
					?>
					<h2><?php echo evo_lang_get('evoTX_014','Your event Tickets','',$this->opt2);?></h2>
					<p><?php evo_lang_e('Once the order is processed your event tickets will show here!');?></p>
					<?php
				}			
			}

		}
	
	// product name on WC order email
		function order_item_name($item_name, $item){
			$_product = wc_get_product($item['variation_id'] ? $item['variation_id'] : $item['product_id']);
			$event_id = get_post_meta($_product->get_id() , '_eventid', true);
			$startDate = get_post_meta($event_id, 'evcal_srow', true);

			if(!empty($startDate)){
				//$date_addition = date('F j(l)', $startDate);
				return $item_name;
			}else{
				return $item_name;
			}
		}

		// replace ticket order item meta data key label with correct translated text
		function order_item_meta($html, $item, $args){
			if( strpos($html, 'Event-Time') == false) return $html;

			$html = str_replace('Event-Time', $this->langX('Event Time','evoTX_005a') , $html);
			$html = str_replace('Event-Location', $this->langX('Event Location','evoTX_005c') , $html);
			return $html;
		}

		function order_item_meta_alt($array){
			$updated_array = $array;
			foreach($array as $index=>$field){
				if( isset($field['label'])){
					if( strpos($field['label'], 'Event-Time') !== false){
						$updated_array[$index]['label'] = str_replace('Event-Time', $this->langX('Event Time','evoTX_005a') , $field['label']);						
					}
					if( strpos($field['label'], 'Event-Location') !== false){
						$updated_array[$index]['label'] = str_replace('Event-Location', $this->langX('Event Location','evoTX_005a') , $field['label']);						
					}
				}
			}

			return $updated_array;
		}

	// styles are scripts
		function styles(){
			global $evotx;
			ob_start();
			include_once($evotx->plugin_path.'/assets/tx_styles.css');
			echo ob_get_clean();
		}
		public function load_styles(){
			global $evotx;

			wp_register_script('tx_wc_variable', $evotx->assets_path.'tx_wc_variable.js', array('jquery'), $evotx->version, true);
			wp_register_script('tx_wc_tickets', $evotx->assets_path.'tx_script.js', array('jquery'), $evotx->version, true);

			wp_enqueue_script('tx_wc_variable');
			wp_enqueue_script('tx_wc_tickets');
			
			// localize script data
			$script_data = array_merge(array( 
					'ajaxurl' => admin_url( 'admin-ajax.php' )
				), $this->get_script_data());

			wp_localize_script( 
				'tx_wc_tickets', 
				'evotx_object',$script_data	
			);
		}
		public function register_styles_scripts(){	
			global $evotx;	
			$evOpt = $this->opt2;
			
			// load style file to page if concatenation is not enabled
			if( evo_settings_val('evcal_concat_styles',$evOpt, true))	
				wp_register_style( 'evo_TX_styles',$evotx->assets_path.'tx_styles.css', array(), $evotx->version);

			$this->print_scripts();
			add_action( 'eventon_enqueue_styles', array($this,'print_styles' ));				
		}
		public function print_scripts(){
			// /wp_enqueue_script('evo_TX_ease');
			//wp_enqueue_script('evo_RS_mobile');	
			//wp_enqueue_script('evo_TX_script');	
		}
		function print_styles(){
			wp_enqueue_style( 'evo_TX_styles');	
		}
		
		/**
		 * Return data for script handles
		 * @access public
		 * @return array|bool
		 */
		function get_script_data(){
			global $evotx;

			$ticket_redirect = evo_settings_value($this->eotx,'evotx_wc_addcart_redirect');
			$wc_redirect_cart = get_option( 'woocommerce_cart_redirect_after_add' );
			if( empty($ticket_redirect) && $wc_redirect_cart == 'yes') 
				$ticket_redirect = 'cart';

			return array(
				'cart_url'=> wc_get_cart_url(), 
				'checkout_url'=> wc_get_checkout_url(), 
				//'cart_url'=>get_permalink( wc_get_page_id( 'cart' ) ),
				'redirect_to_cart'=> $ticket_redirect
			);
		}

	// FRONT END event card inclusion
		function frontend_box($object, $helpers){

			global $evotx, $woocommerce;

			$eventPMV = $object->epmv;

			// if only loggedin users can see
			if( evo_settings_check_yn($evotx->evotx_opt, 'evotx_loggedinuser')  &&  !is_user_logged_in() ){
				return $this->for_none_loggedin($helpers, $object);
				return;
			}

			// initiate event
			$event = new evotx_event($object->event_id, $object->epmv, $object->repeat_interval);

			$wcid = $event->wcid;
			

			//echo $event->test().'yy';

			// if event ticets enable
			if( $event->check_yn('evotx_tix') && $wcid ):

				// get options array
				$woo_product_id = $wcid;
				$woometa = $event->wcmeta;

				// SET UP Global WC Product
				global $product;
				//if ( ! is_object( $product)) 
				$product = wc_get_product( $wcid );

				// get the woocommerce product
					$product = wc_get_product($wcid);

				// check if repeat interval is active for this event
					$ri_count_active = $event->is_ri_count_active();
					
					// check if tickets in stock for this instance of the event
					// returns the capacity for this repeating instance of the event
					// if variable event then dont check for this
					$tix_inStock = ( $product && $product->is_type( 'variable' ) )? true: $event->has_tickets();

				// check if the event is current event
					$isCurrentEvent = $event->is_current_event();

				// if set to stop selling tickets X min before event
					$stopSelling = apply_filters('evotx_stop_selling', $event->is_stop_selling_now() , $object);
				
				$opt = $helpers['evoOPT2'];	

				//echo $tix_inStock? 'in stock':'soldout';


			ob_start();

				$data_attr = array(
					'event_id'=>$object->event_id,
					'tx'=>'',
					'ri'=>$object->repeat_interval,
				);
				$str = '';
				foreach($data_attr as $k=>$v){
					$str .= "data-".$k."='". (empty($v)?'':$v)."' ";
				}

				$class_names = array('evorow','evcal_evdata_row','bordb', 'evcal_evrow_sm','evo_metarow_tix');
				$class_names[] = $helpers['end_row_class'];

				// show remaining stock
					if(!$evotx->functions->show_remaining_stock($eventPMV, $woometa))
						$class_names[] = 'hide_remains';

				$helper = new evo_helper();
				$tix_helpder = new evotx_helper();


			?>

				<div class='<?php echo implode(' ', $class_names);?>' <?php echo $str;?>>
					<span class='evcal_evdata_icons'><i class='fa <?php echo get_eventON_icon('evcal__evotx_001', 'fa-tags',$helpers['evOPT'] );?>'></i></span>
					<div class='evcal_evdata_cell'>							
						<h3 class='evo_h3'><?php $this->langEX('Ticket Section Title', 'evoTX_001');?></h3>
						<p class='evo_data_val'><?php echo evo_meta($woometa,'_tx_text');?></p>	
								
						<?php
							// ticket image id - if exists
							$_tix_image_id = $event->get_prop('_tix_image_id');
						?>
						<div class='evoTX_wc <?php echo ($_tix_image_id)? 'tximg':'';?>' data-si='<?php echo !empty($woometa['_sold_individually'])? $woometa['_sold_individually'][0]: '-';?>' >
							<div class='evoTX_wc_section'>
								
								<?php if(!empty($woometa['_tx_subtiltle_text']) ):?>
									<p class='evo_data_val evotx_description'><?php echo evo_meta($woometa,'_tx_subtiltle_text');?></p>	
								<?php endif;?>

								<?php
									// if show whos coming enabled
									if($evotx->functions->show_whoscoming($eventPMV)):
										
										$guest_list = $this->guest_list($object->event_id, $object->repeat_interval);  

										if($guest_list):
									?>
									<div class='evotx_guest_list'>
										<h4 class='evo_h4'><?php $this->langE('Guest List');?>  <em>(<?php $this->langE('Attending');?>: <?php echo ' '.$guest_list['count'];?>)</em></h4>
										<?php								
											if($guest_list){
												echo "<p class='evotx_whos_coming' style='padding-top:5px;margin:0'><em class='tooltip'></em>" . $guest_list['guests'] . "</p>";
											}
										?>
									</div>
								<?php endif; endif;?>
								
								<div class='evotx_ticket_purchase_section'>
							<?php 
							
								if($isCurrentEvent && !$stopSelling && !empty($woometa['_regular_price'])):

									if ( !$tix_inStock || !empty($woometa['_stock_status']) && $woometa['_stock_status'][0]=='outofstock') :
										echo "<p class='evotx_soldout'>";
										$this->langEX('Sold Out!', 'evoTX_012');
										echo "</p>";
									else:										
										// SIMPLE product
										if( $product && $product->is_type( 'simple' ) ):

											// Locate Template
											$template = $helper->template_locator(
												apply_filters('evotx_single_addtocart_templates', array(
													$evotx->addon_data['plugin_path'].'/templates/'
												), $object->event_id, $woo_product_id),
												'template-add-to-cart-single.php',
												$evotx->addon_data['plugin_path'].'/templates/template-add-to-cart-single.php'
											);

											ob_start();
											include($template);
											$content = ob_get_clean();
											echo apply_filters('evotx_add_cart_section',$content, $object->event_id, $woo_product_id, $product);
											
										endif; // end simple product

										// VARIABLE Product
										if( $product && $product->is_type( 'variable' ) ):
											
											include($evotx->addon_data['plugin_path'].'/templates/template-add-to-cart-variable.php');

										endif;
									endif; // is_in_stock()	
							
									// show remaining tickets or not
									if(
										$tix_inStock &&
										$event->check_yn('_show_remain_tix') &&
										evo_check_yn($woometa,'_manage_stock') 
										&& !empty($woometa['_stock']) 
										&& $woometa['_stock_status'][0]=='instock' 
										&& 
										( (!empty($eventPMV['remaining_count']) 
											&& (int)$eventPMV['remaining_count'][0] >= $woometa['_stock'][0]
											) ||
											empty($eventPMV['remaining_count'])
										)
										&& 
										($product && $product->is_type( 'simple' ))
									){

										// get the remaining ticket 
										// count for event
										// show this remaining total only for simple events
										$remaining_count = (int)$tix_inStock;

										echo "<p class='evotx_remaining' data-count='{$remaining_count}'><span>" . $remaining_count . "</span> ";
										$this->langEX('Tickets remaining!', 'evoTX_013');
										echo "</p>";
									}
								
									// inquire before buy form
									include('html-ticket-inquery.php');

								else: // if the event is a past event

									echo "<p class='evotx_pastevent'>";
									$this->langEX('Tickets are not available for sale any more for this event!', 'evoTX_012a');
									echo "</p>";

									// if show next available for repeating event
									if($event->is_repeating_event() && $event->check_yn('_evotx_show_next_avai_event')){
										$next_available_repeat = $event->next_available_ri($object->repeat_interval);

										if(!empty($next_available_repeat)){
											echo "<p class='evotx_next_event'>";
											echo "<a class='evcal_btn' href='".$event->get_permalink($next_available_repeat['ri']) ."'>". evo_lang('Next Available Event') . "</a>";
											echo "</p>";
										}
									}

								endif; // end current event check							
							?>

								</div><!-- evotx_ticket_purchase_section-->
							</div><!-- .evoTX_wc_section -->
							<?php 
								// content for ticket image seciton
								if($_tix_image_id):
								$img_src = ($_tix_image_id)? 
									wp_get_attachment_image_src($_tix_image_id,'full'): null;
								$tix_img_src = (!empty($img_src))? $img_src[0]: null;
							?>
								<div class='evotx_image'>
									<img src='<?php echo $tix_img_src;?>'/>
									<?php if(!empty($eventPMV['_tx_img_text'])):?>
										<p class='evotx_caption'><?php echo $eventPMV['_tx_img_text'][0];?></p>
									<?php endif;?>
								</div><div class="clear"></div>
							<?php endif;?>
						</div>						
					</div>

					<?php $newWind = (evo_settings_check_yn($evotx->evotx_opt,'evotx_cart_newwin'))? 'target="_blank"':'';?>
					
					<div class='tx_wc_notic' style='display:none'>
						<p class="error" ><?php $this->langEX('You can not buy more than available tickets, please try again!','evoTX_009a');?></p>
						<div class='evo-success'><?php
							echo $tix_helpder->add_to_cart_html();
						?></div>
					</div>
					
				<?php echo $helpers['end'];?> 
				</div>


			<?php 
			$output = ob_get_clean();

			return $output;
			endif;
		}


		// for not loggedin users
			function for_none_loggedin($helpers, $object){
				global $eventon;
				$lang = (!empty($eventon->evo_generator->shortcode_args['lang'])? $eventon->evo_generator->shortcode_args['lang']:'L1');
				ob_start();
				
				?>
				<div class='evorow evcal_evdata_row bordb evcal_evrow_sm evo_metarow_tix <?php echo $helpers['end_row_class']?>' data-tx='' data-event_id='<?php echo $object->event_id ?>' data-ri='<?php echo $object->repeat_interval; ?>'>
					<span class='evcal_evdata_icons'><i class='fa <?php echo get_eventON_icon('evcal__evotx_001', 'fa-tags',$helpers['evOPT'] );?>'></i></span>
					<div class='evcal_evdata_cell'>							
						<h3 class='evo_h3'><?php $this->langEX('Ticket Section Title', 'evoTX_001');?></h3>
						
					<?php
						$txt_1 = evo_lang('You must login to buy tickets!',$lang, $helpers['evoOPT2']);
						$txt_2 = evo_lang('Login Now',$lang, $helpers['evoOPT2']);
						echo "<p>{$txt_1}  ";

						$login_link = wp_login_url(get_permalink());

						// check if custom login lin kprovided
							if(!empty($this->opt1['evo_login_link']))
								$login_link = $this->opt1['evo_login_link'];

						echo apply_filters('evo_login_button',"<a class='evotx_loginnow_btn evcal_btn' href='".$login_link ."'>{$txt_2}</a>", $login_link, $txt_2);
						echo "</p>";

				?></div></div><?php

				return ob_get_clean();
			}

		// inquire form fields
			function inquire_fields(){
				$opt = $this->opt2;

				return apply_filters('evotx_inquiry_fields', array(
					'name'=>array('text',eventon_get_custom_language($opt, 'evoTX_inq_02','Your Name')),					
					'email'=>array('text',eventon_get_custom_language($opt, 'evoTX_inq_03','Email Address')),
					'phone'=>array('text',eventon_get_custom_language($opt, 'evoTX_inq_04a','Phone Number')),			
					'message'=>array('textarea',eventon_get_custom_language($opt, 'evoTX_inq_04','Question'))
				));
			}

		// Guest list
			function guest_list($evneton_id, $repeat_interval=0){
				global $evotx;

				$list = $evotx->functions->get_customer_ticket_list($evneton_id, '', $repeat_interval);
				$output = array();
				$guestListInitials = false;

				//print_r($list);
				$guest_count = 0;
				if($list){
					$eventGuests = array();
					foreach($list as $guest){
						foreach($guest as $field=>$value){
							//$gravatar_link = 'http://www.gravatar.com/avatar/' . md5($value['email']) . '?s=32';
							
							if($value['order_status']!='completed') continue;

							$qty = isset($eventGuests[$value['email']]['qty'])? $eventGuests[$value['email']]['qty']+ $value['qty']: $value['qty'];
							$eventGuests[$value['email']] = array(
								'name'=> $value['name'],
								'tids'=> $value['tids'],
								'qty'=> $qty,
							);							
						}
					}

					// HTML values
					foreach($eventGuests as $email=>$guest){
						$nameData = ($guestListInitials)?
								substr($guest['name'], 0, 1):
								$guest['name'];
						$spaces = (int)$guest['qty'];
						$guest_count += $spaces;
						$output[$email] = apply_filters('evotx_guestlist_guest',"<span class='fullname' data-name='{$guest['name']}". ($spaces>1? ' (+'.($spaces-1).')':'' )."' >{$nameData}</span>", $guest);
					}

				}

				if(count($output)<1) return false;

				return array(
					'guests'=>implode('', $output),
					'count'=>$guest_count
				);
			}

		// event card inclusion functions
			function eventcard_array($array, $pmv, $eventid, $repeat_interval){
				$array['evotx']= array(
					'event_id' => $eventid,
					'repeat_interval'=>$repeat_interval,
					'epmv'=>$pmv
				);
				return $array;
			}
			function eventcard_adds($array){
				$array[] = 'evotx';
				return $array;
			}

	// get language fast for evo_lang
		function lang($text){	return evo_lang($text, '', $this->opt2);}
		function langE($text){ echo $this->lang($text); }
		function langX($text, $var){	return eventon_get_custom_language($this->opt2, $var, $text);	}
		function langEX($text, $var){	echo eventon_get_custom_language($this->opt2, $var, $text);		}
	// get event neat times - 1.1.10
		function get_proper_time($event_id, $ri){
			global $evotx;
			$time = $evotx->functions->get_event_time('', $ri, $event_id);			
	    	return $time;
		}
}