<?php
/**
 * Ticket meta boxes for event page
 *
 * @author 		AJDE
 * @category 	Admin
 * @package 	EventON/Admin/evo-tix
 * @version     1.3
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class EVOTX_post_meta_boxes{
	public function __construct(){
		$this->debug = true;
		add_action( 'add_meta_boxes', array($this, 'evotx_meta_boxes') );
		add_action('eventon_save_meta',  array($this, 'evotx_save_ticket_info'), 10, 2);
		add_action('save_post',array($this, 'save_evotix_post'), 10, 2);
		add_action('save_post', array($this, 'evotx_new_ticket_order_save'), 20,2);
		add_filter('evo_repeats_admin_notice', array($this, 'repeat_notice'), 10,2);

			}
	/** Init the meta boxes. */
		function evotx_meta_boxes(){
			global $post, $pagenow;
			add_meta_box('evotx_mb1', __('Event Tickets','evotx'), array($this,'evotx_metabox_content'),'ajde_events', 'normal', 'high');
			add_meta_box('evo_mb1',__('Event Ticket','evotx'), array($this,'evotx_metabox_002'),'evo-tix', 'normal', 'high');
			
			// check if the order post is a ticket order before showing meta box
			if($post->post_type=='shop_order'){
				$order_type = get_post_meta($post->ID, '_order_type', true);
				if(!empty($order_type) && $order_type=='evotix')
					add_meta_box('evotx_mb1','Event Tickets', array($this,'evotx_metabox_003'),'shop_order', 'side', 'default');
			}

			// when adding a new ticket order from backend
			if($post->post_type=='shop_order' && $pagenow=='post-new.php'){
				add_meta_box('evotx_mb1x','Event Ticket Order Settings', array($this,'evotx_metabox_003x'),'shop_order', 'side', 'default');
			}
			add_meta_box('evotx_mb2',__('Event Ticket Confirmation','evotx'), array($this,'evoTX_notifications_box'),'evo-tix', 'side', 'default');
			do_action('evotx_add_meta_boxes');	
		}
	// repeat notice on event edit post
		function repeat_notice($string, $pmv){
			if(evo_check_yn($pmv,'_manage_repeat_cap') )
				$string .= __('IMPORTANT: Ticket stock for each repeating instances is enabled, changes made to repeating instances may effect the stock for each repeat instance!','evotx');
			return $string;
		}

	// META box on WC Order post
		// adding manual order from backend
			function evotx_metabox_003x(){
				global $ajde;

				?><div id='evotx_new_order'>					
					<p class='yesno_row evo'><?php echo $ajde->wp_admin->html_yesnobtn(array(
						'id'=>'_order_type',
						'default'=>'',
						'label'=> __('Is this a ticket order ?','evotx'),
						'guide'=> __('Check this is the order contain event tickets. The order must NOT contain repeating events as repeating event tickets are not fully compatible for adding from backend, and they must be added from frontend only.','evotx'),
						'guide_position'=>'L',
						'input'=>true
					));
					?></p>
				</div>
				<?php

			}
			// save value
			function evotx_new_ticket_order_save($post_id, $post){
				if($post->post_type!='shop_order')	return;
				if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
				if (defined('DOING_AJAX') && DOING_AJAX) return;

				if(isset($_POST['_order_type']) ){
					update_post_meta($post_id,'_order_type', $_POST['_order_type']);

					if($_POST['_order_type'] == 'yes'){
						global $evotx;

						$evotx->functions->create_tickets($post_id);
						$evotx->functions->alt_initial_event_order($post_id);
					}
				}
			}
		// in WC order post
			function evotx_metabox_003(){


				global $post;

				$order_id = $post->ID;
				$orderPMV = get_post_custom($order_id);
				
				$tixEmailSent = (!empty($orderPMV['_tixEmailSent']) && $orderPMV['_tixEmailSent'][0]==true)? true:false;

				//print_r($orderPMV);
				
				do_action('evotx_beforesend_tix_email', array(), $order_id);

				?>
				<p style=''>
				<?php echo __('Initial Ticket Email','evotx') .': <span style="background-color:#efefef; padding:1px 5px; border-radius:5px;">'. (($tixEmailSent)? __('Sent','evotx'): __('Not Sent','evotx'));?>
				</span></p>

				<?php if($post->post_status =='wc-completed'):?>
				<div class='evoTX_resend_conf'>			
					<div class='evoTX_rc_in'>
						<p><i><?php _e('You can re-send the Event Ticket confirmation email to customer if they have not received it. Make sure to check spam folder.','evotx');?></i></p>
						<a id='evoTX_resend_email' class='evoTX_resend_email button' data-orderid='<?php echo $post->ID;?>'><?php _e('Re-send Ticket(s) Email','evotx');?></a>
						

						<p style='padding-top:5px'>
							<span><?php _e('Send Ticket(s) Email to custom Email','evotx');?>
							<input style='width:100%' type='text' name='customemail' placeholder='<?php _e('Type Email Address','evotx');?>'/>
							<a id='evoTX_resend_email' class='evoTX_resend_email button customemail' style='margin-top:5px;' data-orderid='<?php echo $post->ID;?>'><?php _e('Send Ticket(s) Email','evotx');?></a>
						</p>

						<p class='message' style='display:none; text-align:center;' data-s='<?php _e('Ticket Email Re-send!','evotx');?>' data-f='<?php _e('Could not send email.','evotx');?>'></p>
					</div>
				</div>
				<?php
					else:
						echo '<p style="background-color:#FFEDD7; padding:1px 5px; border-radius:5px; text-align:center;">'.__('Ticket(s) Order is Not Completed Yet!','evotx')."</p>";
					endif;
				?>

				<?php
				// ticket IDs of the order
					if(!empty($orderPMV['_tixids'])){
						echo "<p style='padding-top:10px; font-weight:bold;'>".__('Event Ticket Numbers for this Order','evotx');
						$ticketnumbers = unserialize($orderPMV['_tixids'][0]);
						if(is_array($ticketnumbers)){
							foreach($ticketnumbers as $ticket){
								$tixSplit = explode('-', $ticket);
								echo '<a style="margin-top:5px;" href="'.get_edit_post_link($tixSplit[0]).'" class="button">'.$ticket."</a> ";
							}
						}else{
							echo $ticketnumbers;
						}
						echo "</p>";
					}
				
				// Ticket Meta Data
				/*
				echo "<p style='padding-top:10px; font-weight:bold;'>".__('Event Meta Data','evotx');

				$order = new WC_Order($post->ID);
				foreach( $order->get_items() as $item ) {
					echo $item['name']. ' - '. $item['Event-Time'];
					//print_r($item);
				}
				*/


			}

		// in evo-tix post
			function evoTX_notifications_box(){
				global $post;

				$order_id = get_post_meta($post->ID, '_orderid', true);

				?>
				<div class='evoTX_resend_conf'>
					<div class='evoTX_rc_in'>
						<p><i><?php _e('You can re-send the Event Ticket confirmation email to customer if they have not received it. Make sure to check spam folder.','evotx');?></i></p>
						<a id='evoTX_resend_email' class='evoTX_resend_email button' data-orderid='<?php echo $order_id;?>'><?php _e('Re-send Ticket(s) Email','evotx');?></a>
						<p class='message' style='display:none; text-align:center;' data-s='<?php _e('Ticket Email Re-send!','evotx');?>' data-f='<?php _e('Could not send email.','evotx');?>'></p>
					</div>
				</div>
				<?php
			}

	// META BOX for ticket item post type CPT = evo-tix
		function evotx_metabox_002(){
			global $post, $evotx;

			wp_nonce_field( plugin_basename( __FILE__ ), 'evo_noncename_tix' );

			$ticketItem_meta = $evotix_meta = get_post_meta($post->ID);
			$event_id = !empty($ticketItem_meta['_eventid'])? $ticketItem_meta['_eventid'][0]:'';			
			$event_meta = get_post_meta($event_id);	

			$evotx_tix = new evotx_tix();

			// Debug email templates
				if($this->debug):
					
					$order_id = $ticketItem_meta['_orderid'][0];
					$order = new WC_Order( $order_id);
					$tickets = $order->get_items();

					


					$order_tickets = $evotx_tix->get_ticket_numbers_for_order($order_id);
					
					//$tixh = $evotx_tix->get_order_ticket_holders($order_id);
					//print_r($tixh);

					$email_body_arguments = array(
						'orderid'=>$order_id,
						'tickets'=>$order_tickets, 
						'customer'=>'Ashan Jay',
						'email'=>'yes'
					);

					$email = new evotx_email();
					$tt = $email->get_ticket_email_body($email_body_arguments);
					print_r($tt);

					//EVOPDF()->gen_pdf_file('ticket',$order_id);
				endif;

			// get event times			
				$event_time = $evotx->functions->get_event_time($event_meta, (!empty($ticketItem_meta['repeat_interval'])? $ticketItem_meta['repeat_interval'][0]:0) );

				// get corrected event ticket ids
					if(empty($ticketItem_meta['ticket_ids'][0])){
						$evotx->functions->correct_tix_ids($ticketItem_meta, $post->ID);					
					}
			?>	
			<div class='eventon_mb' style='margin:-6px -12px -12px'>
			<div style='background-color:#ECECEC; padding:15px;'>
				<div style='background-color:#fff; border-radius:8px;'>
				<table width='100%' class='evo_metatable' cellspacing="" style='vertical-align:top' valign='top'>
					<tr><td><?php _e('Woocommerce Order ID','evotx');?> #: </td><td><?php 

						// order status
						$_orderid = get_post_meta($post->ID, '_orderid', true);		
						$order_status = $evotx->functions->get_order_status($_orderid);

						echo '<a class="button" href="'.get_edit_post_link($ticketItem_meta['_orderid'][0]).'">'.$ticketItem_meta['_orderid'][0].'</a> <span style="display:inline-block; padding:5px 0 0 10px;">'.$order_status.'</span>';
					?></td></tr>
					
					<tr><td><?php _e('Ticket Type','evotx');?>: </td><td><?php echo (!($ticketItem_meta['type'])? $ticketItem_meta['type'][0]:'--');?></td></tr>

					<?php
						$primary_ticket_holder = (!empty($ticketItem_meta['_customerid'][0]) && $ticketItem_meta['_customerid'][0] != '0')? '<a href="'.get_edit_user_link($ticketItem_meta['_customerid'][0]).'">'.$ticketItem_meta['name'][0].'</a>': $ticketItem_meta['name'][0];
					?>
					<tr><td><?php _e('Ticket Holder(s) for the order','evotx');?>: </td>
						<td><?php 
							echo $primary_ticket_holder . __(' (Purchased By)','evotx');


							$order_ticket_holders = $evotx_tix->get_order_ticket_holders($_orderid);
							$add_ticket_holder = array();
							if(!empty($order_ticket_holders)){
								$add_ticket_holder = $evotx_tix->get_ticket_holders_forevent( $event_id,$order_ticket_holders);

								echo '<p style="">'.implode(', ',$add_ticket_holder ) . '</p>';	
							}
						?>
						</td></tr>
					<tr><td><?php _e('Email Address','evotx');?>: </td><td><?php echo $ticketItem_meta['email'][0];?></td></tr>
					<tr><td><?php _e('Quantity','evotx');?>: </td><td><?php echo $ticketItem_meta['qty'][0];?></td></tr>
					<tr><td><?php _e('Cost for ticket(s)','evotx');?>: </td><td><?php echo get_woocommerce_currency_symbol().$ticketItem_meta['cost'][0];?></td></tr>
					<tr><td><?php _e('Event','evotx');?>: </td>
					<td><?php echo '<a class="button" href="'.get_edit_post_link($event_id).'">'.get_the_title($ticketItem_meta['_eventid'][0]).'</a>';?> 
						<?php
							// if this is a repeat event show repeat information						
							if(!empty($event_meta['evcal_repeat']) && $event_meta['evcal_repeat'][0]=='yes'){
								echo "<p>".__('This is a repeating event','evotx')."</p>";
							}
						?>
					</td></tr>
					<tr><td><?php _e('Ticket Time','evotx');?>: </td><td><?php echo $event_time;?></td></tr>
					<?php
					// get translated checkin status
						$st_count = $evotx_tix->checked_count($post->ID);
						$status = $evotx_tix->get_checkin_status_text('checked');
						$__count = ': '.(!empty($st_count['checked'])? $st_count['checked']:'0').' out of '.$ticketItem_meta['qty'][0];
					?>				
					<tr><td><?php _e('Ticket Checked-in Status','evotx');?>: </td><td><?php echo $status.$__count; ?></td></tr>
					<tr><td ><?php _e('Ticket(s)','evotx');?> #: </td><td>
						<?php 
						// get ticket number for this ticket post
							$ticket_numbers = $evotx_tix->get_ticket_numbers_by_evotix($post->ID);
						?>
						<table id='evotx_ticketItem_tickets'>
							<?php 
								if(is_array($ticket_numbers)):

									$index = 0;
									foreach($ticket_numbers as $ticket_number=>$status):

										// get ticket holder names by number
										$ticket_holder = $evotx_tix->get_ticket_holder_by_ticket_number($ticket_number,$_orderid);

									?>
									<tr><td><?php echo  apply_filters('evotx_tixPost_tixid', $ticket_number);?>
										<?php if($ticket_holder):?>
											<span class='evotx_attendee_name'> - <?php echo $ticket_holder;?> </span>
										<?php endif;?>
									<br/>

									<?php	if($order_status == 'completed'):	?>
										<span class='tix_status <?php echo $status;?>' data-tiid='<?php echo $post->ID;?>' data-tid='<?php echo $ticket_number;?>' data-status='<?php echo $status;?>'><?php echo $evotx_tix->get_checkin_status_text($status);?></span>
									<?php endif;?>

									</td></tr>
							<?php 
										$index++;
									endforeach;
								else:
									echo "<tr><td>{$ticket_numbers}</td></tr>";
								endif;
							?>
						</table>
					</td></tr>
					<?php
						do_action('eventontx_tix_post_table',$post->ID, $ticketItem_meta);
					?>
				</table>
				</div>
			</div>
			</div>
			<?php
		}
		// save evo-tix post values
			function save_evotix_post($post_id, $post){
				if($post->post_type!='evo-tix')	return;				
				if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
				if (defined('DOING_AJAX') && DOING_AJAX)	return;
				
				// verify this came from the our screen and with proper authorization,
				// because save_post can be triggered at other times
				if( isset($_POST['evo_noncename_tix']) ){
					if ( !wp_verify_nonce( $_POST['evo_noncename_tix'], plugin_basename( __FILE__ ) ) ){
						return;
					}
				}
				// Check permissions
				if ( !current_user_can( 'edit_post', $post_id ) )	return;	

				global $pagenow;
				$_allowed = array( 'post-new.php', 'post.php' );
				if(!in_array($pagenow, $_allowed)) return;

				foreach(array(
					'_admin_notes',
				) as $variable){
					if(!empty($_POST[$variable])){
						update_post_meta( $post_id, $variable,$_POST[$variable]);
					}elseif(empty($_POST[$variable])){
						delete_post_meta($post_id, $variable);
					}
				}
				
			}

	//  Event META BOX for ajde_events CPT */	
		function evotx_metabox_content(){
			global $post, $evotx, $eventon, $evotx_admin;
			$woometa='';

			$event_id = $post->ID;

			// need evo 2.6.1
			$EVENT = new EVO_Event($post->ID);
			$fmeta = $EVENT->get_data();
			
			//$fmeta = get_post_meta($event_id);			
			
			$woo_product_id = (!empty($fmeta['tx_woocommerce_product_id']))? $fmeta['tx_woocommerce_product_id'][0]:null;

			// if the wc product exists
			if($woo_product_id)
				if( !$evotx_admin->post_exist($woo_product_id) ) $woo_product_id = null;

			// get options
			$evoOpt = get_evoOPT_array(1);

			// if woocommerce ticket has been created
			$the_product = '';
			if($woo_product_id){
				$woometa =  get_post_custom($woo_product_id);
				$the_product = wc_get_product($woo_product_id);
			}
			$__woo_currencySYM = get_woocommerce_currency_symbol();


			ob_start();

			$evotx_tix = (!empty($fmeta['evotx_tix']))? $fmeta['evotx_tix'][0]:null;
			$repeat_intervals = !empty($fmeta['repeat_intervals'])? unserialize($fmeta['repeat_intervals'][0]): false;
			$isCurrentEvent = $evotx->functions->is_currentEvent($fmeta, $repeat_intervals);

			
			?>
			<div class='eventon_mb' data-eid='<?php echo $event_id;?>'>
			<div class="evotx">
				<input type='hidden' name='tx_woocommerce_product_id' value="<?php echo $woo_product_id;?>"/>
				<p class='yesno_leg_line ' style='padding:10px'>
					<?php echo eventon_html_yesnobtn(array('id'=>'evotx_activate','var'=>$evotx_tix, 
						'attr'=>array('afterstatement'=>'evotx_details'))); ?>				
					<input type='hidden' name='evotx_tix' value="<?php echo ($evotx_tix=='yes')?'yes':'no';?>"/>
					<label for='evotx_tix'><?php _e('Activate tickets for this Event','evotx'); echo $eventon->throw_guide('You can allow ticket selling via Woocommerce for this event in here.','',false); ?></label>
				</p>
				<div id='evotx_details' class='evotx_details evomb_body ' <?php echo ( $evotx_tix=='yes')? null:'style="display:none"'; ?>>
					<?php
						$product_type = 'simple';

						// product type
						$product_type = $evotx_admin->get_product_type($woo_product_id);
						$product_type = (!empty($product_type))? $product_type: 'simple';
					?>
					
					<div class="evotx_tickets" >
					
						<h4><?php _e('Ticket Info for this event','evotx');?></h4>
						<?php
							// ticket event date notice
							if(!$repeat_intervals && !$isCurrentEvent):
								echo "<p style='padding: 10px 25px;'><i>".__('IMPORTANT: Event must have current or future event date for ticket purchasing information to display on front-end!','evotx')."</i></p>";
							endif;
						?>				
						<?php 
							
							$tickets_instock = $evotx->functions->get_tix_instock($woometa);
							$ticket_stats = $evotx->functions->get_customer_ticket_list($event_id, $woo_product_id, 'all','order_status');
							
							if(!empty($ticket_stats)):
								$denominator = (int)$tickets_instock + (int)$ticket_stats['total'];
								foreach(array('notchecked','pending','refunded','checked') as $status){
									if($status == 'notchecked'){
										$top = $ticket_stats['completed']- $ticket_stats['checked'];
										$data['notchecked'] = ($top!=0)?(($top/$denominator)*100):0;
									}else{
										$data[$status] = ($ticket_stats[$status]!=0)?(($ticket_stats[$status]/$denominator)*100):0;
									}									
								}
							?>
							<div class="evotx_ticket_data">
								<div class="evotx_stats_bar">
									<p class='evotx_stat_subtitle' ><?php _e('Event Ticket Order Data','evotx');?></p>
									<p class='stat_bar'>
										<span class="checked" style='width:<?php echo $data['checked'];?>%'></span>
										<span class="notchecked" style='width:<?php echo $data['notchecked'];?>%'></span>
										<span class="pending" style='width:<?php echo $data['pending'];?>%'></span>
										<span class="refunded" style='width:<?php echo $data['refunded'];?>%'></span>
									</p>

									<p class="evotx_stat_text">
										<span><em class='checked'></em><?php _e('Checked','evotx');?>: <?php echo $ticket_stats['checked'];?></span>
										<span><em class='notchecked'></em><?php _e('Not-checked','evotx');?>: <?php echo $ticket_stats['completed'] - $ticket_stats['checked'];?></span>
										<?php if($ticket_stats['pending']>0):?>
											<span><em class='pending'></em><?php _e('Pending','evotx');?>: <?php echo $ticket_stats['pending'];?></span>
										<?php endif;?>
										<?php if($product_type == 'simple'):?>
											<span><em class='instock'></em><?php _e('In Stock','evotx');?>: <?php echo $tickets_instock;?></span>
										<?php endif;?>
									</p>
								</div>
							</div>
						<?php endif; ?>

						<table class='eventon_settings_table' width='100%' border='0' cellspacing='0'>
							<?php if(!empty($product_type)):?>
								<tr><td><?php _e('Ticket Pricing Type','evotx');?></td><td><?php echo  $product_type;?></td></tr>
							<?php endif;?>

							<input type='hidden' name='tx_product_type' value='<?php echo $product_type;?>'/>

							<!-- Price-->
							<?php if(!empty($product_type) && !empty($the_product) && $product_type=='variable'):
							?>
								<tr><td><?php printf( __('Ticket price (%s)','evotx'), $__woo_currencySYM);?></td><td><p><?php echo $the_product->get_price_html() ? $the_product->get_price_html() : '<span class="na">&ndash;</span>';?></p>
								<p class='marb20'><a href='<?php echo get_edit_post_link($woo_product_id);?>' style='color:#fff'><?php _e('Edit Price Variations')?></a></p></td></tr>				
								
							<?php else:?>
								<!-- Regular Price-->
								<tr><td><?php printf( __('Ticket price (%s)','evotx'), $__woo_currencySYM);?></td><td><input type='text' id='_regular_price' name='_regular_price' value="<?php echo evo_meta($woometa, '_regular_price');?>"/></td></tr>

								<!-- Sale Price-->
								<tr><td><?php printf( __('Sale price (%s)','evotx'), $__woo_currencySYM);?></td><td><input type='text' id='_sale_price' name='_sale_price' value="<?php echo evo_meta($woometa, '_sale_price');?>"/></td></tr>
							<?php endif;?>

							<?php do_action('evotx_edit_event_ticket_tablerow', $post->ID, $woo_product_id);?>						

							<!-- SKU-->
							<tr><td><?php echo __('Ticket SKU', 'evotx').' '.__('(Required*)', 'evotx'); echo $eventon->throw_guide('SKU refers to a Stock-keeping unit, a unique identifier for each distinct menu item that can be ordered. You must enter a SKU or else the tickets might not function correct.','',false);?></td><td><input type='text' name='_sku' value='<?php echo evo_meta($woometa, '_sku');?>'/></td></tr>

							<!-- Desc-->
							<tr><td><?php _e('Short Ticket Detail', 'evotx'); ?></td><td><input type='text' name='_tx_desc' value='<?php echo evo_meta($woometa, '_tx_desc');?>'/></td></tr>
							
							<!-- manage capacity -->
								<?php
									$_manage_cap = evo_meta_yesno($woometa,'_manage_stock','yes','yes','no' );
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_mcap',
										'var'=>$_manage_cap, 'attr'=>array('afterstatement'=>'exotc_cap'))); ?>
										<input type='hidden' name='_manage_stock' value="<?php echo $_manage_cap;?>"/>
										<label for='_manage_stock'><?php _e('Manage Ticket Stock','evotx')?></label>
									</p>
								</td></tr>
							
							<!-- Capacity -->
								<?php
								//$tt = get_post_meta('273');
								//print_r($tt);
								?>
								<tbody id='exotc_cap' class='innersection' style='display:<?php echo evo_meta_yesno($woometa,'_manage_stock','yes','','none' );?>'>
								<tr ><td><?php _e('Total Tickets in Stock','evotx'); echo $eventon->throw_guide('This is how many tickets you have currently in stock.','',false)?></td><td><input type='text' id="_stock" name="_stock" value="<?php echo evo_meta($woometa, '_stock');?>"/></td></tr>
										
				
							<!-- Manage Capcity seperate for repeating events -->
								<?php

									if(!empty($fmeta['evcal_repeat']) && $fmeta['evcal_repeat'][0]=='yes' && $product_type=='simple'
										&& $repeat_intervals && count($repeat_intervals)>0
									):
									$manage_repeat_cap = evo_meta_yesno($fmeta,'_manage_repeat_cap','yes','yes','no' );

								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_mcap',
										'var'=>$manage_repeat_cap, 'attr'=>array('afterstatement'=>'evotx_ri_cap'))); ?>
										<input type='hidden' name='_manage_repeat_cap' value="<?php echo $manage_repeat_cap;?>"/>

										<label for='_manage_repeat_cap'><?php _e('Manage capacity seperate for each repeating event'); echo $eventon->throw_guide('This will show remaining tickets for this event on front-end','',false)?></label>
									</p>
									<div id='evotx_ri_cap' class='evotx_repeat_capacity' style='padding-top:15px; padding-bottom:20px;display:<?php echo evo_meta_yesno($fmeta,'_manage_repeat_cap','yes','','none' );?>'>
										<p><em style='opacity:0.6'><?php _e('NOTE: The capacity above should match the total number of capacity for each repeat occurance below for this event. Capacity is not supported for repeating events that have variations.','evotx');?></em></p>
										<?php
											$count =0;

											// get saved capacities for repeats
											$ri_capacity = !empty($fmeta['ri_capacity'])?
												unserialize($fmeta['ri_capacity'][0]): false;

											//print_r($ri_capacity);
											//print_r($repeat_intervals);

											echo "<div class='evotx_ri_cap_inputs'>";
											// for each repeat interval
											foreach($repeat_intervals as $interval){
												$TIME  = $evotx_admin->get_format_time($interval[0]);

												echo "<p style='display:" . ( ($count>4)?'none':'block') . "'><input type='text' name='ri_capacity[]' value='". (($ri_capacity && !empty($ri_capacity[$count]))? $ri_capacity[$count]:'0') . "'/> " . $TIME[0] . "</p>";
												$count++;
											}

											echo "</div>";

											echo (count($repeat_intervals)>5)? 
												"<p class='evotx_ri_view_more'><a class='button_evo'>Click here</a> to view the rest of repeat occurances.</p>":null;
										?>
									</div>
								</td></tr>
								<?php endif;?>

							<!-- show remaining -->
								<?php
									$remain_tix = evo_meta_yesno($fmeta,'_show_remain_tix','yes','yes','no' );
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_mcap',
										'var'=>$remain_tix, 'attr'=>array('afterstatement'=>'evotx_showre_count'))); ?>
										<input type='hidden' name='_show_remain_tix' value="<?php echo $remain_tix;?>"/>
										<label for='_show_remain_tix'><?php _e('Show remaining tickets','evotx'); echo $eventon->throw_guide('This will show remaining tickets for this event on front-end','',false)?></label>
									</p>
								</td></tr>
								<tr id='evotx_showre_count' style='display:<?php echo evo_meta_yesno($fmeta,'_show_remain_tix','yes','','none' );?>'><td><?php _e('Show remaining count at','evotx'); echo $eventon->throw_guide('Show remaining count when remaining count go below this number.','',false);?></td><td><input type='text' id="remaining_count" name="remaining_count" placeholder='20' value="<?php echo evo_meta($fmeta, 'remaining_count');?>"/></td></tr>	
								</tbody>
							
							
							<!-- Show guest list on eventCard -->
								<?php
									$_tx_show_guest_list = ( !empty($fmeta['_tx_show_guest_list']) && $fmeta['_tx_show_guest_list'][0]=='yes')? 'yes':'no';
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line '>
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_mcap',
										'var'=>$_tx_show_guest_list, 'no'=>'no')); ?>
										<input type='hidden' name='_tx_show_guest_list' value="<?php echo $_tx_show_guest_list;?>"/>
										<label for='_tx_show_guest_list'><?php _e('Show guest list for event on eventCard', 'evotx'); ?></label>
									</p>
								</td></tr>	

							<!-- make ticket out of stock -->
								<?php
									$_stock_status = ( !empty($woometa['_stock_status']) && $woometa['_stock_status'][0]=='outofstock')? 'outofstock':'instock';
									$_stock_status_yesno = ( $_stock_status=='outofstock')? 'yes':'no';
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line '>
										<?php echo eventon_html_yesnobtn(
											array('id'=>'evotx_mcap',
											'var'=>$_stock_status_yesno, 
											'no'=>'no')); 
										?>
										<input type='hidden' name='_stock_status' value="<?php echo $_stock_status_yesno;?>"/>
										<label for='_stock_status'><?php _e('Place ticket on out of stock', 'evotx'); echo $eventon->throw_guide('Set stock status of tickets. Setting this to yes would make tickets not available for sale anymore. This will also add sold out tag into event top, if not disabled in eventon settings.','',false)?></label>
									</p>
								</td></tr>	
							<!-- Catalog Visibility -->
								<?php
								/*
									$visibility = evo_meta_yesno($fmeta,'visibility','yes','yes','no' );
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_mcap','var'=>$visibility,)); ?>				
										<input type='hidden' name="visibility" value="<?php echo $visibility;?>"/>
										<label for='visibility'><?php _e('Woocommerce Catalog Visibility', 'evotx'); echo $eventon->throw_guide('Make the ticket product visible in woocommerce products page and catalog','',false)?></label>
									</p>
								</td></tr>	
							<?php */?>
							<!-- sold individually -->
								<?php
									$_sold_ind = evo_meta_yesno($woometa,'_sold_individually','yes','yes','no' );
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_mcap','var'=>$_sold_ind,)); ?>				
										<input type='hidden' name="_sold_individually" value="<?php echo $_sold_ind;?>"/>
										<label for='_sold_individually'><?php _e('Sold Individually', 'evotx'); echo $eventon->throw_guide( __('Enable this to only allow one ticket per person','evotx'),'',false)?></label>
									</p>
								</td></tr>	

							<!-- show next available event -->
								<?php
									if(!empty($fmeta['evcal_repeat']) && $fmeta['evcal_repeat'][0]=='yes' 
										&& $repeat_intervals && count($repeat_intervals)>0
									):

									$_evotx_show_next_avai_event = evo_meta_yesno($fmeta,'_evotx_show_next_avai_event','yes','yes','no' );
								?>
								<tr><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'_evotx_show_next_avai_event','var'=>$_evotx_show_next_avai_event,)); ?>				
										<input type='hidden' name="_evotx_show_next_avai_event" value="<?php echo $_evotx_show_next_avai_event;?>"/>
										<label for='_evotx_show_next_avai_event'><?php _e('Show next available repeating instance of event', 'evotx'); echo $eventon->throw_guide( __('This will allow a visitor to see the next available event in the repeating events series, if current repeating event is past and not available for sale.','evotx'),'',false)?></label>
									</p>
								</td></tr>	

								<?php endif;?>

							<!-- close before X minuted -->
								<tr><td >
									<?php _e('Stop selling tickets X minutes before event start','evotx'); echo $eventon->throw_guide( __('This will hide selling tickets options X minutes before the event start. X can be in minuted in the input field.','evotx'),'',false);?></td><td><input type='text' id="_xmin_stopsell" name="_xmin_stopsell" placeholder='20' value="<?php echo evo_meta($fmeta, '_xmin_stopsell');?>"/>
								</td></tr>


							<!-- Field details-->
								<tr><td style='padding:5px 25px;' colspan='2'><?php _e('Ticket Section Subtitle', 'evotx'); echo $eventon->throw_guide('This text will appear right under the ticket section title in eventcard','',false);?><br/>
									<textarea style='width:100%; margin-top:5px'name='_tx_text'><?php echo evo_meta($woometa, '_tx_text');?></textarea>
								</td></tr>

							<!-- Field details-->
								<tr><td style='padding:5px 25px;' colspan='2'><?php _e('Ticket Field description', 'evotx'); echo $eventon->throw_guide('Use this to type instruction text that will appear above add to cart section on calendar.','',false);?><br/>
									<textarea style='width:100%; margin-top:5px'name='_tx_subtiltle_text'><?php echo evo_meta($woometa, '_tx_subtiltle_text');?></textarea>
								</td></tr>

							<!-- ticket image -->
								<?php
									// tix_image_id
									$_tix_image_id = (!empty($fmeta['_tix_image_id'])? 
										$fmeta['_tix_image_id'][0]:false);
									// image soruce array
									$img_src = ($_tix_image_id)? 
										wp_get_attachment_image_src($_tix_image_id,'medium'): null;
									$tix_img_src = (!empty($img_src))? $img_src[0]: null;

									// button texts & Class names
										$__button_text = (!empty($_tix_image_id))? __('Remove Image','evotx'): __('Choose Image','evotx');
										$__button_text_not = (empty($_tix_image_id))? __('Remove Image','evotx'): __('Choose Image','evotx');
										$__button_class = (!empty($_tix_image_id))? 'removeimg':'chooseimg';
								?>
								<tr><td style='padding:5px 25px;' colspan='2'>
									<div class='evo_metafield_image' style='padding-top:10px'>
										<p >
											<label style='padding-bottom:5px; display:inline-block'><?php _e('Ticket Image','evotx');?></label><br/>
											<i style='opacity:0.6'>NOTE: Ticket image added here will show next to add to cart section on event card. This image will also go in the WC ticket product as featured image. DO NOT set featured images for WC Ticket product, as that will get removed and replaced with this image.</i><br/><br/>
											<span style=''></span>
											<input id='_tix_image_id' class='custom_upload_image evo_meta_img' name="_tix_image_id" type="hidden" value="<?php echo ($_tix_image_id)? $_tix_image_id: null;?>" /> 
				                    		<input class="custom_upload_image_button button <?php echo $__button_class;?>" data-txt='<?php echo $__button_text_not;?>' type="button" value="<?php echo $__button_text;?>" /><br/>
				                    		<span class='evo_tx_image_src image_src'>
				                    			<img src='<?php echo $tix_img_src;?>' style='<?php echo !empty($_tix_image_id)?'':'display:none';?>'/>
				                    		</span>		                    		
				                    	</p>
				                    	<?php _e('Ticket Image Caption', 'evotx'); echo $eventon->throw_guide('Caption text that will appear under ticket image.','',false);?><br/><input style='width:100%; margin-top:5px'type='text' name='_tx_img_text' value='<?php echo evo_meta($fmeta, '_tx_img_text');?>'/>
				                    </div>
								</td></tr>

							<?php
							// inquire before buying
								$_allow_inquire = evo_meta_yesno($fmeta,'_allow_inquire','yes','yes','no' );
								$_tx_inq_subject = (!empty($fmeta['_tx_inq_subject']))? $fmeta['_tx_inq_subject'][0]: 
									( !empty($evoOpt['evotx_tix_inquiries_def_subject'])? $evoOpt['evotx_tix_inquiries_def_subject']: 'New Ticket Sale Inquery');
								$_tx_inq_email = (!empty($fmeta['_tx_inq_email']))? $fmeta['_tx_inq_email'][0]: 
									( !empty($evoOpt['evotx_tix_inquiries_def_email'])? $evoOpt['evotx_tix_inquiries_def_email']: get_option('admin_email') );
							?>
							<!-- INQUIRY SECTION -->
								<tr ><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'evotx_showinq',
										'var'=>$_allow_inquire, 'attr'=>array('afterstatement'=>'evotx_show_inq'))); ?>
										<input type='hidden' name='_allow_inquire' value="<?php echo $_allow_inquire;?>"/>
										<label for='_allow_inquire'><?php _e('Allow customers to submit inquiries.','evotx'); echo $eventon->throw_guide('With this customers can submit inquiries via this form before buying tickets on front-end.','',false)?></label>
									</p>
								</td></tr>
								<tr class='innersection' id='evotx_show_inq' style='display:<?php echo evo_meta_yesno($fmeta,'_allow_inquire','yes','','none' );?>'><td colspan='2'><?php _e('Override Default Email Address to receive Inquiries', 'evotx'); ?><br/>
									<input style='width:100%; margin-top:5px'type='text' name='_tx_inq_email' placeholder='<?php echo $_tx_inq_email;?>' value='<?php echo $_tx_inq_email;?>'/>
									<?php _e('Override Default Subject for Inquiries Email', 'evotx'); ?><br/>
									<input style='width:100%; margin-top:5px'type='text' name='_tx_inq_subject' placeholder='<?php echo $_tx_inq_subject;?>' value='<?php echo evo_meta($fmeta, '_tx_inq_subject');?>'/>
									<p style='padding-top:5px;opacity:0.6'><i><?php _e('NOTE: Front-end fields for Inquiries form can be customized from','evotx');?> <a style='color:#B3DDEC' href='<?php echo admin_url();?>admin.php?page=eventon&tab=evcal_2'>EventON Languages</a></i></p>
								</td></tr>	
							
							<!-- Manually attach associate WC product-->
							<?php
								/*$_evotx_manual_product = evo_meta_yesno($fmeta,'_evotx_manual_product','yes','yes','no' );
							?>
								<tr ><td colspan='2'>
									<p class='yesno_leg_line ' >
										<?php echo eventon_html_yesnobtn(array('id'=>'_evotx_manual_product',
										'var'=>$_evotx_manual_product, 'attr'=>array('afterstatement'=>'evotx_manual_product'))); ?>
										<input type='hidden' name='_evotx_manual_product' value="<?php echo $_evotx_manual_product;?>"/>
										<label for='_evotx_manual_product'><?php _e('Manually set associated WC ticket product'); echo $eventon->throw_guide('With this you can manually assign a woocommerce product as the ticket product that will be associated with this event as the ticket product, if a WC product was not auto generated.','',false)?></label>
									</p>
								</td></tr>
								<tr class='innersection' id='evotx_manual_product' style='display:<?php echo evo_meta_yesno($fmeta,'_evotx_manual_product','yes','','none' );?>'>
									<td colspan='2'>
										<label><?php _e('Select the associated woocommerce ticket product', 'evotx'); ?></label>
										<?php
											global $wp_query;
											$wc_product = new WP_Query(array(
												'post_type'=>'product',
												'post_per_page'=>-1,
												'tax_query' => array(
													array(
														'taxonomy' => 'product_cat',
														'field'    => 'slug',
														'terms'    => 'ticket',
													),
												),
											));

											if($wc_product->have_posts()):
												while($wc_product->have_posts()): $wc_product->the_post();
													echo get_the_title();
													wp_reset_query();
												endwhile;
												wp_reset_postdata();
											endif;
										?>
										<input style='width:100%; margin-top:5px'type='text' name='_tx_inq_email' placeholder='<?php echo $_tx_inq_email;?>' value='<?php echo $_tx_inq_email;?>'/>
									
								</td></tr>	
							<?php */?>	

							<?php do_action('evotx_event_metabox_end', $event_id, $fmeta,  $woo_product_id, $product_type);?>					

						</table>
						<?php if($woo_product_id):?>
							<p class='actions'>
								<a class='button_evo edit' href='<?php echo get_edit_post_link($woo_product_id);?>'  title='<?php _e('Further Edit ticket product from woocommerce product page','evotx');?>'> <?php _e('Further Edit','evotx');?></a> <i style='color:#fff'><?php _e('Learn More','evotx');?>: <a style='color:#fff' href='http://www.myeventon.com/documentation/set-variable-prices-tickets/' target='_blank'><?php _e('How to add variable price tickets','evotx');?></a></i>
							</p>
								
							<p class='actions'>
								<a class='button_evo ajde_popup_trig evotx_manual_wc_prod'  data-popc='evotx_manual_wc_product' data-eid='<?php echo $event_id;?>' data-wcid='<?php echo $woo_product_id;?>'><?php _e('Assign Different WC Product as Ticket Product','eventon');?></a>
							</p>
						<?php endif;?>
						<div class='clear'></div>		
					</div>						
					<?php
						// lightbox content for view attendees	
						$ri_count_active = $evotx->functions->is_ri_count_active($fmeta, $woometa);
						$datetime = new evo_datetime();	$wp_date_format = get_option('date_format');

						if($repeat_intervals && $ri_count_active && count($repeat_intervals)>0):
							ob_start();?>
							<div id='evotx_view_attendees' class='evotx_select_repeating_instance'>
								<p style='text-align:center'><label><?php _e('Select Repeating Instance of Event','evotx');?></label> 
									<select name="" id="evotx_event_repeatInstance">
										<option value="all"><?php _e('All Repeating Instances','evotx');?></option>
										<?php
										$x=0;								
										foreach($repeat_intervals as $interval){
											$time = $datetime->get_correct_formatted_event_repeat_time($fmeta,$x, $wp_date_format);
											echo "<option value='".$x."'>".$time['start']."</option>"; $x++;
										}
										?>
									</select>
								</p>
								<p style='text-align:center'><a id='evotx_VA_submit' data-e_id='<?php echo $post->ID;?>'  data-wcid='<?php echo evo_meta($fmeta, 'tx_woocommerce_product_id');?>' class='evo_admin_btn btn_prime' ><?php _e('Submit','evotx');?></a> </p>
							</div>
							<div id='evotx_view_attendees_list'></div>
							<?php $viewattendee_content = ob_get_clean();
						else:	
							$viewattendee_content = "<p class='evo_lightbox_loading'></p>";	
						endif;
					?>
					
					<?php 					


					// lightbox content for emailing section
						ob_start();?>
						<div id='evotx_emailing' style=''>
							<p><label><?php _e('Select emailing option','evotx');?></label>
								<select name="" id="evotx_emailing_options">
									<option value="someone"><?php _e('Email Attendees List to someone','evotx');?></option>
									<option value="completed"><?php _e('Email only to completed order guests','evotx');?></option>
									<option value="pending"><?php _e('Email only to pending order guests','evotx');?></option>
								</select>
							</p>
							<?php
								// if repeat interval count separatly						
								if($ri_count_active && $repeat_intervals ){
									if(count($repeat_intervals)>0){
										echo "<p><label>". __('Select Event Repeat Instance','evotx')."</label> ";
										echo "<select name='repeat_interval' id='evotx_emailing_repeat_interval'>
											<option value='all'>".__('All','evotx')."</option>";																
										$x=0;								
										foreach($repeat_intervals as $interval){
											$time = $datetime->get_correct_formatted_event_repeat_time($fmeta,$x, $wp_date_format);
											echo "<option value='".$x."'>".$time['start']."</option>"; $x++;
										}
										echo "</select>";
										echo $eventon->throw_guide("Select which instance of repeating events of this event you want to use for this emailing action.", '',false);
										echo "</p>";
									}
								}
							?>
							<p style='' class='text'><label for=""><?php _e('Email Addresses (separated by commas)','evotx');?></label><br/><input style='width:100%' type="text"></p>
							<p style='' class='subject'><label for=""><?php _e('Subject for email','evotx');?> *</label><br/><input style='width:100%' type="text"></p>
							<p style='' class='textarea'><label for=""><?php _e('Message for the email','evotx');?></label><br/>
								<textarea cols="30" rows="5" style='width:100%'></textarea></p>
							<p><a data-eid='<?php echo $event_id;?>' data-wcid='<?php echo $woo_product_id;?>' id="evotx_email_submit" class='evo_admin_btn btn_prime'><?php _e('Send Email','evotx');?></a></p>
						</div>
					<?php $emailing_content = ob_get_clean();?>

					<?php 
						// Lightboxes
						global $ajde;
						
						echo $ajde->wp_admin->lightbox_content(array(
							'class'=>'evotx_lightbox', 
							'content'=>$viewattendee_content, 
							'title'=>__('View Attendee List','evotx'), 
							'type'=>'padded', 
							'max_height'=>500 
						));

						echo $ajde->wp_admin->lightbox_content(array(
							'class'=>'evotx_email_attendee', 
							'content'=>$emailing_content, 
							'title'=>__('Email Attendee List','evotx'), 
							'type'=>'padded' 
						));

						echo $ajde->wp_admin->lightbox_content(array(
							'class'=>'evotx_manual_wc_product', 
							'content'=> '<p class="evo_lightbox_loading"></p>', 
							'title'=>__('Assign Manual WC Product','evotx'), 
							'type'=>'padded' 
						));

						// DOWNLOAD CSV link 
							$exportURL = add_query_arg(array(
							    'action' => 'the_ajax_evotx_a3',
							    'e_id' => $post->ID,
							    'pid'=> $woo_product_id
							), admin_url('admin-ajax.php'));
					?>

					<!-- Attendee section -->
						<?php if(!empty($woometa['total_sales']) && $woometa['total_sales']>0):?>
						<div class='evoTX_metabox_attendee_other'>
							<p><?php _e('Additional Options for Event Attendance','evotx');?></p>
							<p class="actions">
								<a id='evotx_attendees' data-eid='<?php echo $event_id;?>' data-riactive='<?php echo ($ri_count_active && $repeat_intervals)?'yes':'no';?>' data-wcid='<?php echo evo_meta($fmeta, 'tx_woocommerce_product_id');?>' data-popc='evotx_lightbox' class='button_evo attendees ajde_popup_trig' title='<?php _e('View Attendees','evotx');?>'><?php _e('View Attendees','evotx');?></a>
								<a class='button_evo download' href="<?php echo $exportURL;?>"><?php _e('Download (CSV)','evotx');?></a>
								<a id='evotx_EMAIL' data-e_id='<?php echo $event_id;?>' data-popc='evotx_email_attendee' class='button_evo email ajde_popup_trig' ><?php _e('Emailing','evotx');?></a> 
								<a href='<?php echo get_admin_url('','/admin.php?page=eventon&tab=evcal_5');?>'class='button_evo troubleshoot ajde_popup_trig' title='<?php _e('Troubleshoot RSVP Addon','evotx');?>'><?php _e('Troubleshoot','evotx');?></a> 
							</p>

						</div>
						<?php endif;?>
				</div>			
			</div>
			</div>

			<?php
			echo ob_get_clean();
		}

	// save new ticket and create matching WC product
		function evotx_save_ticket_info($arr, $post_id){			

			global $evotx_admin, $evotx;

			// if allowing woocommerce ticketing
			if(!empty($_POST['evotx_tix']) && $_POST['evotx_tix']=='yes'){
				// check if woocommerce product id exist
				if(isset($_POST['tx_woocommerce_product_id'])){

					$post_exists = $evotx_admin->post_exist($_POST['tx_woocommerce_product_id']);

					// make sure woocommerce stock management is turned on
						update_option('woocommerce_manage_stock','yes');
										
					// add new
					if(!$post_exists){
						$wcid = $evotx->functions->add_new_woocommerce_product($post_id);
					}else{
						$wcid = (int)$_POST['tx_woocommerce_product_id'];
						$evotx_admin->update_woocommerce_product($wcid, $post_id);
					}	

					$this->save_stock_status($wcid);
					
				// if there isnt a woo product associated to this - add new one
				}else{
					$evotx->functions->add_new_woocommerce_product($post_id);
				}
			}

			foreach(apply_filters('evotx_save_eventedit_page', array(
				'_tx_img_text',
				'evotx_tix', 
				'_show_remain_tix', 
				'remaining_count', 
				'_manage_repeat_cap', 
				'_tix_image_id', 
				'_allow_inquire',
				'_tx_inq_email',
				'_tx_inq_subject',
				'_xmin_stopsell',
				'_tx_show_guest_list',
				'_evotx_show_next_avai_event',
			)) as $variable){
				if(!empty($_POST[$variable])){
					update_post_meta( $post_id, $variable,$_POST[$variable]);
				}elseif(empty($_POST[$variable])){

					if($variable == '_tix_image_id' && !empty($_POST['evotx_tix']) && $_POST['evotx_tix']=='yes' && !empty($_POST['tx_woocommerce_product_id'])){
						delete_post_thumbnail( (int)$_POST['tx_woocommerce_product_id']);
					}
					delete_post_meta($post_id, $variable);
				}
			}

			// after saving event tickets data
			do_action('evotx_after_saving_ticket_data', $post_id);

			// repeat interval capacities
				if(!empty($_POST['ri_capacity']) && evo_settings_check_yn($_POST, '_manage_repeat_cap')){

					// get total
					$count = 0; 
					foreach($_POST['ri_capacity'] as $cap){
						$count = $count + ( (int)$cap);
					}
					// update product capacity
					update_post_meta( $_POST['tx_woocommerce_product_id'], '_stock',$count);
					update_post_meta( $post_id, 'ri_capacity',$_POST['ri_capacity']);
				}
		}

		function save_stock_status($wcid){
			$_stock_status = (!empty($_POST['_stock_status']) && $_POST['_stock_status']=='yes')? 'outofstock': 'instock';
			update_post_meta($wcid, '_stock_status', $_stock_status);
		}



}
new EVOTX_post_meta_boxes();

	



