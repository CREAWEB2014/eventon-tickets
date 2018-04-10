<?php
/**
 * Intergration with ActionUser Addon
 * @version 1.3.8
 * @actionuser_version 2.0.10
 */
class evotx_actionuser{
	public function __construct(){

		add_filter('evoau_form_fields', array($this, 'fields_to_form'), 10, 1);

		// only for frontend
		// actionUser intergration
		add_action('evoau_frontform_evotx', array($this, 'fields'), 10, 6);	

		add_action('evoau_save_formfields', array($this, 'save_values'), 10, 3);
		add_action('evoau_frontend_scripts_enqueue', array($this, 'enqueue_scripts'), 10);

		// event manager
		add_action('evoau_manager_row_title', array($this, 'event_manager_row_title'), 10, 2);
		add_action('evoau_manager_row', array($this, 'event_manager_row'), 10, 3);
		add_action('evoauem_custom_action', array($this, 'event_manager_show_data'), 10, 1);

		// ajax filters
		add_action( 'wp_ajax_evotx_ajax_get_auem_stats', array( $this, 'evors_ajax_get_auem_stats' ) );
		add_action( 'wp_ajax_nopriv_evotx_ajax_get_auem_stats', array( $this, 'evors_ajax_get_auem_stats' ) );

		// only admin fields
		if(is_admin()){
			add_filter('eventonau_language_fields', array($this, 'language'), 10, 1);
		}
	}

	// include ticket script
		function enqueue_scripts(){
			wp_enqueue_script('tx_wc_tickets');
		}

	// include fields to submission form array
		function fields_to_form($array){
			$array['evotx']=array('Ticket Fields', 'evotx_tix', 'evotx','custom','');
			return $array;
		}

	// Frontend showing fields and saving values  
		function fields($field, $event_id, $default_val, $EPMV, $opt2, $lang){

			$form = new evoau_form();

			$evotx_tix = ($EPMV && !empty($EPMV['evotx_tix']) && $EPMV['evotx_tix'][0]=='yes')? true: false;
			
			echo $form->get_form_html(
				'evotx_tix',
				array(
					'type'=>'yesno',
					'yesno_args'=>array(
						'id'=>'evotx_tix',
						'input'=>true,
						'label'=>evo_lang('Sell tickets for this event', $lang, $opt2),
						'var'=> ($evotx_tix?'yes':'no'),
						'lang'=>$lang,
						'afterstatement'=>'evotx_data_section'
					)
				)
			);			
			

			// for editting
				$_regular_price = $_sale_price = $_stock = $_sku = $product = $woometa = '';
				$wc_ticket_product_id = !empty($EPMV['tx_woocommerce_product_id'])? $EPMV['tx_woocommerce_product_id'][0]: false;
				if($wc_ticket_product_id){
					$woometa = get_post_custom($wc_ticket_product_id);

					$product = wc_get_product($wc_ticket_product_id);
					
					if(!empty($woometa['_regular_price']) )	$_regular_price = $woometa['_regular_price'][0];
					if(!empty($woometa['_sale_price']) )	$_sale_price = $woometa['_sale_price'][0];
					if(!empty($woometa['_stock']) )	$_stock = $woometa['_stock'][0];
					if(!empty($woometa['_sku']) )	$_sku = $woometa['_sku'][0];
				}

				// non simple item notice
				if( ($product && !$product->is_type('simple'))){
					$au_tx_fields_array['non_simple_notice']= array(
						'content'=>evo_lang('This is a non-simple WC Ticket, must contact admin to make further edits!')
					);
				}
				$au_tx_fields_array['tx_product_type']=array(
					'type'=>'hidden',
					'value'=>'simple',
					'form_type'=>'new',						
				);
				$au_tx_fields_array['visibility']=array(
					'type'=>'hidden',
					'value'=>'visible',
					'form_type'=>'new',						
				);
				$au_tx_fields_array['tx_woocommerce_product_id']=array(
					'type'=>'hidden',
					'value'=>$wc_ticket_product_id,
					'form_type'=>'edit',						
				);
				$au_tx_fields_array['tx_woocommerce_product_id'] = array(
					'type'=>'hidden',
					'value'=>$wc_ticket_product_id,
					'form_type'=>'edit',						
				);
				$au_tx_fields_array['_regular_price'] = array(
					'type'=>	'text',
					'name'=>	evo_lang('Ticket Price',$lang, $opt2),
					'value'=>	evo_var_val($woometa, '_regular_price'),
					'required_html'=> 	' *',
					'required_class'=>	' req',
					'req_dep'=>	array('name'=>'evotx_tix','value'=>'yes')
				);
				$au_tx_fields_array['_sale_price']=array(
					'type'=>	'text',
					'name'=>	evo_lang('Ticket Sales Price',$lang, $opt2),
					'value'=>	evo_var_val($woometa, '_sale_price'),
				);
				$au_tx_fields_array['_sold_individually']=array(
					'type'=>	'yesno',
					'yesno_args'=> array(
						'id'=>'_sold_individually',
						'input'=>true,
						'label'=>evo_lang('Sold Individually'),
						'var'=> (evo_check_yn($woometa, '_sold_individually')?'yes':'no'),
						'lang'=>$lang,
						'guide'=> evo_lang('Enable this to only allow one ticket per person')
					)
				);
				$au_tx_fields_array['_sku'] = array(
					'type'=>	'text',
					'name'=>	evo_lang('SKU',$lang, $opt2),
					'value'=>	evo_var_val($woometa, '_sku'),
					'tooltip'=>	evo_lang('SKU refers to a Stock-keeping unit, a unique identifier for each distinct menu item that can be ordered. You must enter a SKU or else the tickets might not function correct.')
				);

				$au_tx_fields_array['_stock']= array(
					'type'=>	'text',
					'name'=>	evo_lang('Ticket Stock Capacity',$lang, $opt2),
					'value'=>	evo_var_val($woometa, '_stock'),
				);
				$au_tx_fields_array['_show_remain_tix']=array(
					'type'=>	'yesno',
					'yesno_args'=> array(
						'id'=>'_show_remain_tix',
						'input'=>true,
						'label'=>evo_lang('Show remaining tickets'),
						'var'=> (evo_check_yn($woometa, '_show_remain_tix')?'yes':'no'),
						'lang'=>$lang,
						'guide'=> evo_lang('This will show remaining tickets for this event on front-end')
					)
				);
				$au_tx_fields_array['remaining_count']= array(
					'type'=>	'text',
					'name'=>	evo_lang('Show remaining count at',$lang, $opt2),
					'value'=>	evo_var_val($woometa, 'remaining_count'),
					'tooltip'=>	evo_lang('Show remaining count when remaining count go below this number.')
				);
				$au_tx_fields_array['_tx_show_guest_list']=array(
					'type'=>	'yesno',
					'yesno_args'=> array(
						'id'=>'_tx_show_guest_list',
						'input'=>true,
						'label'=>evo_lang('Show guest list for event on eventCard'),
						'var'=> (evo_check_yn($woometa, '_tx_show_guest_list')?'yes':'no'),
						'lang'=>$lang,
					)
				);

			
			echo "<div id='evotx_data_section' class='row evoau_sub_formfield' style='display:".($evotx_tix?'':'none')."'>";

			// print all fields using actionUser function
			foreach($au_tx_fields_array as $field=>$data){
				echo $form->get_form_html($field, $data);
			}

			echo "</div>";
		}

		// save form submission values
		function save_values($field, $fn, $event_id){
			if( $field =='evotx'){					
				if(!empty($_POST['evotx_tix']) && $_POST['evotx_tix']=='yes'){

					global $evotx;

					// adjust $_POST array
						if(!empty($_POST['_stock']))	$_POST['_manage_stock'] = 'yes';

					update_post_meta($event_id, 'evotx_tix', $_POST['evotx_tix']);
					
					if( !empty($_POST['tx_woocommerce_product_id'])){
						$post_exists = $this->post_exist($_POST['tx_woocommerce_product_id']);

						if($post_exists){
							global $evotx_admin;

							$evotx->functions->save_product_meta_values($_POST['tx_woocommerce_product_id'], $event_id);
						}else{
							$evotx->functions->add_new_woocommerce_product($event_id);		
						}
					}else{ // add new 
						$evotx->functions->add_new_woocommerce_product($event_id);		
					}
				}
			}
		}

		function post_exist($ID){
			global $wpdb;

			$post_id = $ID;
			$post_exists = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE id = '" . $post_id . "'", 'ARRAY_A');
			return $post_exists;
		}
	
	// event manager additions
		function event_manager_row_title($event_id, $EPMV){
			$wc_ticket_product_id = !empty($EPMV['tx_woocommerce_product_id'])? $EPMV['tx_woocommerce_product_id'][0]: false;
			
			if(!empty($EPMV['evotx_tix']) && $EPMV['evotx_tix'][0]=='yes' && $wc_ticket_product_id){
				echo "<tags style='background-color:#8BDBEC'>".evo_lang('Ticket Sales On')."</tags>";
			}
		}
		function event_manager_row($event_id, $EPMV){
			$wc_ticket_product_id = !empty($EPMV['tx_woocommerce_product_id'])? $EPMV['tx_woocommerce_product_id'][0]: false;

			if(!empty($EPMV['evotx_tix']) && $EPMV['evotx_tix'][0]=='yes' && $wc_ticket_product_id ){
				echo "<a class='evoauem_additional_buttons load_tix_stats' data-eid='{$event_id}'>".evo_lang('View Ticket Stats')."</a>";
			}
		}

		function evors_ajax_get_auem_stats(){
			$html = $this->event_manager_show_data($_POST['eid']);
			echo json_encode(array(
				'status'=>'good',
				'html'=>$html
			));exit;
		}

		function event_manager_show_data($event_id){

			ob_start();
			$EPMV = get_post_custom($event_id);
			$wc_ticket_product_id = !empty($EPMV['tx_woocommerce_product_id'])? $EPMV['tx_woocommerce_product_id'][0]: false;

			if(!$wc_ticket_product_id) return;
			global $evotx;

			$woometa = get_post_custom($wc_ticket_product_id);
			$product_type = $evotx->functions->get_product_type($wc_ticket_product_id);
			$__woo_currencySYM = get_woocommerce_currency_symbol();

			$evotx_opt = get_option('evcal_options_evcal_tx');
			$allowed_checkin = evo_settings_check_yn($evotx_opt, 'evotx_checkin_guests');


			?>
				<h3 class="evoauem_section_subtitle"><?php evo_lang_e('Event');?>: <b><?php echo get_the_title($event_id);?></b></h3>
				<h3 class='evoauem_section_subtitle' style='margin-bottom:20px'><?php evo_lang_e('Event Ticket Information & Stats');?></h3>	
				<div class="evoautx_data">
					<table>
						<tr><td><?php evo_lang_e('Price');?></td><td><?php
						if($product_type=='variable'){
							echo $__woo_currencySYM . ' '. evo_meta($woometa, '_min_variation_price') .' - '.evo_meta($woometa, '_max_variation_price');
						}else{
							echo $__woo_currencySYM . ' '. evo_meta($woometa, '_regular_price');
						}
						?></td></tr>
						<?php if(evo_check_yn($woometa,'_manage_stock')):?>
							<?php
								if($product_type == 'simple'):
									$tix_inStock = $evotx->functions->event_has_tickets($EPMV, $woometa, 0);
							?>
								<tr><td><?php evo_lang_e('Tickets in stock');?></td><td><?php echo  $tix_inStock;?></td></tr>
							<?php endif;?>
						<?php endif;?>
						<tr><td><?php evo_lang_e('Ticket Type');?></td><td><?php echo $product_type;?></td></tr>
						<tr><td><?php evo_lang_e('SKU');?></td><td><?php echo evo_meta($woometa, '_sku');?></td></tr>
						<?php if(evo_check_yn($woometa,'_manage_stock')):?>
							<tr><td><?php evo_lang_e('Stock Status');?></td><td><?php echo evo_meta($woometa, '_stock_status');?></td></tr>
						<?php endif;?>

						<?php 
							$customer_ = $evotx->functions->get_customer_ticket_list($event_id, $wc_ticket_product_id, 'all');

							// customers with completed orders
							if($customer_){

								// check permissions to check in guests
								//$checkinpermission = current_user_can()


								echo "<tr><td colspan='2'>";
								echo "<h3>".evo_lang_e('Attendees')."</h3>";
								echo "<div class='event_tix_attendee_list'>";

							
								// each event on the repeat
								foreach($customer_ as $event_time=>$tickets){
														
									$indexO = 1;
									$content = array();
									$totalCompleteCount = 0;

									// each ticket Order item
									foreach($tickets as $ticketItem_){
										
										$output = '';

										$order_status = !empty($ticketItem_['order_status'])? $ticketItem_['order_status']: false;
										$key = ($order_status=='completed')?'good':'bad';
										$key_ = ($order_status=='completed')?'':'hidden';
										if($order_status=='completed')$totalCompleteCount += (int)$ticketItem_['qty'];

										// HTML parsing
										$output .= "<tr><td class='evotx_ticketitem_customer ".($indexO%2==0? 'even':'odd')." {$key} {$key_}'>";
										$output .= "<span class='evotx_ticketitem_header'>"
											.'<b>'.$ticketItem_['name'].'</b>  ('.$ticketItem_['email'].') '.( !empty($ticketItem_['type'])? "- <b>{$ticketItem_['type']}</b>":''). 
											( $order_status? " <b class='orderStutus status_{$order_status}'>{$order_status}</b>":'') ."</span>";
										$output .= "<span class='evotx_ticketItem'><span class='txcount'>{$ticketItem_['qty']}</span>";

										$tid = $ticketItem_['tids']; // ticket ID array with status
										

										$output .= "<span class='tixid'>";

										// Ticket Holder information
											$order_ticket_holders = get_post_meta($ticketItem_['orderid'], '_tixholders', true);
											$ticket_holder = $evotx->functions->get_ticketholder_names( $event_id,$order_ticket_holders);
										
										// for each ticket ID
										$index = 0;
										foreach($tid as $id=>$_status){
											$langStatus = $evotx->functions->get_checkin_status($_status);
											$output .= "<span class='evotx_ticket ".($allowed_checkin?'chkb':'nb')."'>".$id;
											if($order_status == 'completed'){
												$output .= "<span class='evotx_status {$_status}' data-tid='{$id}' data-status='{$_status}' data-tiid='{$ticketItem_['tiid']}'>".$langStatus."</span>";

												// Ticket holder name associated to 
												if($ticket_holder && !empty($ticket_holder[$index]))
													$output .= "<span class='evotx_ticket_holdername'>".$ticket_holder[$index]."</span>";
											}
											$output .= "</span>";
											$tidX = $id;
											$index++;
										}

										$tix = explode('-', $tidX);
										$orderID = $tix[1];

										$output .= "<span class='clear'></span>
											<em class='orderdate'>".__('Ordered Date','evotx').': '.$ticketItem_['postdata']."</em>";
											$output .= " <em>".__('Order ID:','evotx')." ".$orderID."</em>";
										$output .= "</span>";


										$output .= "</span>";
										$output .= "</td></tr>";

										
										$content[$key][] = $output;
										$indexO++;
									}


									echo "<table class='attendee'><tbody>";
									echo "<tr><td class='event_time'>".
										__('Event Time:','evotx').' '.$event_time."<em>".__('Total','evotx')." {$totalCompleteCount}</em>".
										"</td></tr>";
									if(!empty($content['good']))	echo implode('', $content['good']);
									echo "<tr><td><span class='separatation evotx_incomplete_orders'>".__('Other incompleted orders','evotx')."</span></tr></td>";
									if(!empty($content['bad'])) echo implode('', $content['bad']);

									echo "</tbody></table>";					
								}
								echo "</div>";
								echo "</tr>";
							}
						?>					
					</table>
				</div>
			<?php

			return ob_get_clean();
		}

	// language
		function language($array){
			$newarray = array(
				array('label'=>'Ticket Fields','type'=>'subheader'),
					array('label'=>'Sell tickets for this event','var'=>'1'),		
					array('label'=>'Ticket Price','var'=>'1'),		
					array('label'=>'Ticket Sales Price','var'=>'1'),		
					array('label'=>'SKU','var'=>'1'),		
					array('label'=>'Ticket Stock Capacity','var'=>'1'),		
					array('label'=>'Event Ticket Information & Stats','var'=>'1'),				
					array('label'=>'Price','var'=>'1'),				
					array('label'=>'Tickets in stock','var'=>'1'),				
					array('label'=>'Tickets Type','var'=>'1'),	
					array('label'=>'Stock Status','var'=>'1'),				
					array('label'=>'Show remaining tickets','var'=>'1'),				
					array('label'=>'This will show remaining tickets for this event on front-end','var'=>'1'),				
					array('label'=>'Show remaining count at','var'=>'1'),				
					array('label'=>'Show remaining count when remaining count go below this number.','var'=>'1'),				
					array('label'=>'Show guest list for event on eventCard','var'=>'1'),				
					array('label'=>'Attendees','var'=>'1'),				
					array('label'=>'Confirmed Attendance','var'=>'1'),				
					array('label'=>'This is a non-simple WC Ticket, must contact admin to make further edits!','var'=>'1'),				
				array('type'=>'togend'),
			);
			return array_merge($array, $newarray);
		}
}
new evotx_actionuser();