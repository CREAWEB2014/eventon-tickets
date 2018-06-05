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
		$this->eotx = EVOTX()->evotx_opt;
		
		// event card inclusion
		add_filter('eventon_eventCard_evotx', array($this, 'frontend_box'), 10, 3);
		add_filter('eventon_eventcard_array', array($this, 'eventcard_array'), 10, 4);
		add_filter('evo_eventcard_adds', array($this, 'eventcard_adds'), 10, 1);

			// event top above title
				add_filter('eventon_eventtop_abovetitle', array($this,'eventtop_above_title'),10, 2);
		
		// scripts and styles 
		add_action( 'init', array( $this, 'register_styles_scripts' ) ,15);	
		add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ), 10 );

		add_action('evo_addon_styles', array($this, 'styles') );

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

				// event have tickets enabled
				if(!empty($epmv['evotx_tix']) && $epmv['evotx_tix'][0]=='yes' && !empty($epmv['tx_woocommerce_product_id'])){

					// dismiss if set in ticket settings not to show sold out tag on eventtop
					$hide_soldout_tag = (evo_settings_check_yn($this->eotx, 'evotx_eventop_soldout_hide'));

					global $evotx;
					$woometa = get_post_custom($epmv['tx_woocommerce_product_id'][0]);

					$haveTix = $evotx->functions->event_has_tickets($epmv, $woometa, $object->ri);
					if(!$haveTix){
						if($hide_soldout_tag) return $var;
						return "<span class='evo_soldout'>".$this->langX('Sold Out!', 'evoTX_012')."</span>";
					}


					// check with settings if event over to be hidden
					$hide_eventover_tag = (evo_settings_check_yn($this->eotx, 'evotx_eventop_eventover_hide'));

					$isCurrentEvent = $evotx->functions->is_currentEvent($epmv, $object->ri);

					if( !$isCurrentEvent){
						if($hide_eventover_tag) return $var;
						return "<span class='eventover'>".$this->langX('Event Over', 'evoTX_012b')."</span>";
					}
				}
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


			// Testing
			//print_r( get_post_meta(1,'aaa2', true));

			// get order id and get order items and check each for status
			/*
			$R = new WC_Order_Refund( 614 );
			//print_r( );
			foreach( $R->get_items() as $item_id=>$item){
				echo 'ITEM:'.$item_id.' ';
				$refuned_item_id = $item->get_meta('_refunded_item_id');
				echo $item->get_quantity();

				$total_item_qty = wc_get_order_item_meta( $refuned_item_id, '_qty',true);
				echo $total_item_qty;
				//print_r($item);

			}

			$order = new WC_Order( 610 );	
			$items = $order->get_items();


			foreach ($items as $item_id => $item) {	
				echo 'Refunded'. $order->get_qty_refunded_for_item($item_id);
				
			}
			*/				
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
				'redirect_to_cart'=> $ticket_redirect,
				'tBlank'=> evo_settings_check_yn($this->eotx,'evotx_cart_newwin')
			);
		}

	// FRONT END event card inclusion
		function frontend_box($object, $helpers, $EVENT){
			
			// globals
				// pass global evo lang to ticket extensions - which use AJAX to load content
				if(!isset($GLOBALS['EVOLANG'])) $GLOBALS['EVOLANG'] = evo_get_current_lang(); 

				global $evotx, $woocommerce, $EVOLANG;
				
			$eventPMV = $object->epmv;

			// if only loggedin users can see
			if( evo_settings_check_yn($evotx->evotx_opt, 'evotx_loggedinuser')  &&  !is_user_logged_in() ){
				return $this->for_none_loggedin($helpers, $object);
				return;
			}
			
			// initiate event
			$event = new evotx_event($EVENT->ID, $object->epmv, $object->repeat_interval);

			$wcid = $event->wcid;

			// if event ticets enable
			if( $event->check_yn('evotx_tix') && $wcid ):

				// get options array
				$woo_product_id = $wcid;
				$woometa = $event->wcmeta;

				// SET UP Global WC Product
				// get the woocommerce product
					$product = $event->product;

				// check if repeat interval is active for this event
					$ri_count_active = $event->is_ri_count_active();
					
					// check if tickets in stock for this instance of the event
					// returns the capacity for this repeating instance of the event
					// if variable event then dont check for this
					$tix_inStock = apply_filters('evotx_is_ticket_in_stock', ( $product && $product->is_type( 'variable' ) )? true: $event->has_tickets(), $event );

				// check if the event is current event
					$isCurrentEvent = $event->is_current_event();

				// if set to stop selling tickets X min before event
					$stopSelling = apply_filters('evotx_stop_selling', $event->is_stop_selling_now() , $object);
				
				$opt = $helpers['evoOPT2'];	

				//print_r(get_post_meta(728));

			ob_start();

				$data_attr = array(
					'event_id'=>$object->event_id,
					'wcid'=>$woo_product_id,
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
				$tix_helper = new evotx_helper();

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
											echo "<p class='evotx_whos_coming' style='padding-top:5px;margin:0'><em class='tooltip'></em>" . $guest_list['guests'] . "</p>";
										?>
									</div>
								<?php endif; endif;?>
								
								<div class='evotx_ticket_purchase_section'>
							<?php 
							
								if($isCurrentEvent && !$stopSelling && !empty($woometa['_regular_price'])):

									if ( !$tix_inStock || $event->is_sold_out() ) :
										echo "<p class='evotx_soldout'>";
										$this->langEX('Sold Out!', 'evoTX_012');
										echo "</p>";
									else:										
										// SIMPLE product
										if( $product && $product->is_type( 'simple' ) ):

											$use_default_temp = apply_filters('evotx_single_product_temp', true, $event, $product);

											if( $use_default_temp === true){
												// Use default single ticket add to cart template
												// deprecating the filter
												$template = $helper->template_locator(
													apply_filters('evotx_single_addtocart_templates', array(
														$evotx->addon_data['plugin_path'].'/templates/'
													), $object->event_id, $woo_product_id),
													'template-add-to-cart-single.php',
													$evotx->addon_data['plugin_path'].'/templates/template-add-to-cart-single.php'
												);

												include($template);
											}else{
												echo $use_default_temp;
											}

																						
										endif; // end simple product

										// VARIABLE Product
										if( $product && $product->is_type( 'variable' ) ):																						
											include($evotx->addon_data['plugin_path'].'/templates/template-add-to-cart-variable.php');

										endif;
									endif; // is_in_stock()	
							
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
		// @updated 1.7
			function guest_list($event_id, $repeat_interval=0){
				
				$EA = new EVOTX_Attendees();
				$TH = $EA->get_tickets_for_event($event_id);
				$total_tickets = 0;
				$output = '';

				if(!$TH || count($TH)<1) return false;

				ob_start();
				$cnt = 0;
				$guests = array();

				//print_r($TH);
				foreach($TH as $tn=>$td){

					// validate
					if(empty($td['name'])) continue;
					if(trim($td['name']) =='') continue;

					// check for RI
					if($td['ri'] != $repeat_interval) continue;
					if(in_array($td['name'], $guests)) continue;

					// skip refunded tickets
					if($td['s'] == 'refunded') continue;
					if($td['oS'] != 'completed') continue;

					$guests[] = $td['name'];
					echo apply_filters('evotx_guestlist_guest',"<span class='fullname' data-name='".$td['name']."' >". $td['name'] ."</span>", $td);
					$cnt++;

				}
				$output = ob_get_clean();			

				return array(
					'guests'=>	$output,
					'count'=>	$cnt
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