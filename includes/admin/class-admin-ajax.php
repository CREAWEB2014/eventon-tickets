<?php
/** 
 * AJAX for only backend of the tickets
 * @version 1.3.10
 */
class evotx_admin_ajax{
	public function __construct(){
		$ajax_events = array(
			'the_ajax_evotx_a1'=>'evotx_get_attendees',			
			'the_ajax_evotx_a3'=>'generate_csv',
			'the_ajax_evotx_a55'=>'admin_resend_confirmation',
			'evoTX_ajax_07'=>'get_ticektinfo_by_ID',
			'the_ajax_evotx_a8'=>'emailing_attendees_admin',
			'evotx_assign_wc_products'=>'assign_wc_products',
			'evotx_save_assign_wc_products'=>'save_assign_wc_products',
		);
		foreach ( $ajax_events as $ajax_event => $class ) {
			add_action( 'wp_ajax_'.  $ajax_event, array( $this, $class ) );
			add_action( 'wp_ajax_nopriv_'.  $ajax_event, array( $this, $class ) );
		}
	}

// assign WC Product to event ticket
	function assign_wc_products(){
		$wc_prods = new WP_Query(array(
				'post_type'=>'product', 
				'posts_per_page'=>-1,
				'tax_query' => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => 'ticket',
					),
				),
			)
		);

		ob_start();

		?><div style='text-align:center;padding:15px'><?php

		if($wc_prods->have_posts()):
			?>
			<p><?php _e('Select a WC Product to assign this event ticket, instead of the already assigned WC Product','evotx');?><br/><br/>
			<i><?php _e('This event ticket is currently assigned to the below WC Product!','eventon');?></i><br/><code> (ID: <?php echo $_POST['wcid'];?>) <?php echo get_the_title($_POST['wcid']);?></code></p>

			<select class='field' name='evotx_wcid'><?php

			while($wc_prods->have_posts()): $wc_prods->the_post();

				$selected = (!empty($_POST['wcid']) && $wc_prods->post->ID == $_POST['wcid'])? 'selected="selected"':'';

				?><option <?php echo $selected;?> value="<?php echo $wc_prods->post->ID;?>">(ID: <?php echo $wc_prods->post->ID;?>) <?php the_title();?></option><?php
			endwhile;

			?></select>

				<br/><br/><p><i><?php _e('NOTE: When selecting a new WC Product be sure the product is published and can be assessible on frontend of your website','evotx');?></i></p>
				<p style='text-align:center; padding-top:10px;'>
					<span class='evo_btn evotx_submit_manual_wc_prod' data-eid="<?php echo $_POST['eid'];?>"><?php _e('Save Changes','eventon');?></span>
				</p>
				<?php

			wp_reset_postdata();
		else:
			?><p><?php _e('You do not have any items saved! Please add new!','eventon');?></p><?php
		endif;

		echo "</div>";

		echo json_encode(array('content'=>ob_get_clean(), 'status'=>'good')); exit;

	}

	function save_assign_wc_products(){
		$wcid = (int)$_POST['wcid'];
		$eid = (int)$_POST['eid'];

		update_post_meta( $eid, 'tx_woocommerce_product_id', $wcid);

		EVOTX()->functions->save_product_meta_values($wcid, $eid);
		EVOTX()->functions->assign_woo_cat($wcid);

		$msg = __('Successfully Assigned New WC Product to Event Ticket!','evotx');

		echo json_encode(array('msg'=> $msg, 'status'=>'good')); exit;
	}

	// GET attendee list view for event
		function evotx_get_attendees(){	
			global $evotx;

			$nonce = $_POST['postnonce'];
			$status = 0;
			$message = $content = '';

			if(! wp_verify_nonce( $nonce, 'evotx_nonce' ) ){
				$status = 1;	$message ='Invalid Nonce';
			}else{

				ob_start();

				$evotx_tix = new evotx_tix();

				$ri = (!empty($_POST['ri']) || $_POST['ri']=='0')? $_POST['ri']:'all'; // repeat interval

				$customer_ = $evotx->functions->get_customer_ticket_list($_POST['eid'], $_POST['wcid'], $ri);

				// customers with completed orders
				if($customer_){
					echo "<div class='evotx'>";

					do_action('evotx_admin_view_attendees', $_POST['eid'], $_POST['wcid']);

					echo "<p class='header'>".__('Attendee Details','evotx')." <span class='txcount'>".__('Ticket Count','evotx')."</span></p>";	
					
					echo "<div class='eventedit_tix_attendee_list'>";

				
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
							$output .= "<span class='evotx_ticketitem_customer ".($indexO%2==0? 'even':'odd')." {$key} {$key_} ". ( isset($ticketItem_['orderid']) ? 'orderid_'.$ticketItem_['orderid']: '') ."' data-orderid='". ( isset($ticketItem_['orderid']) ? $ticketItem_['orderid']: '') ."'>";
							$output .= "<span class='evotx_ticketitem_header'>"
								.'<b>'.$ticketItem_['name'].'</b>  ('.$ticketItem_['email'].') '.( !empty($ticketItem_['type'])? "- <b>{$ticketItem_['type']}</b>":''). 
								( $order_status? " <b class='orderStutus status_{$order_status}'>{$order_status}</b>":'') ."</span>";
							$output .= "<span class='evotx_ticketItem'><span class='txcount'>{$ticketItem_['qty']}</span>";

							$tid = $ticketItem_['tids']; // ticket ID array with status
							

							$output .= "<span class='tixid'>";

							// Ticket Holder information
								$order_ticket_holders = get_post_meta($ticketItem_['orderid'], '_tixholders', true);
								

							// for each ticket ID
							$index = 0;
							foreach($tid as $ticket_number=>$_status){

								$ticket_holder = $evotx_tix->get_ticket_holder_by_ticket_number($ticket_number);

								$langStatus = $evotx->functions->get_checkin_status($_status);
								
								$output .= "<span class='evotx_ticket'>".$ticket_number;
								
								// if the order is completed show checking in interactive button
								if($order_status == 'completed'){
									$output .= "<span class='evotx_status {$_status}' data-tid='{$ticket_number}' data-status='{$_status}' data-tiid='{$ticketItem_['tiid']}'>".$langStatus."</span>";
								}

								// Ticket holder name associated to 
								if($ticket_holder )
									$output .= "<span class='evotx_ticket_holdername'>".$ticket_holder."</span>";
								
								$output .= "</span>";
								$tidX = $ticket_number;
								$index++;
							}

							$tix = explode('-', $tidX);
							$orderID = $tix[1];

							$output .= "<span class='clear'></span>
								<em class='orderdate'>".__('Ordered Date','evotx').': '.$ticketItem_['postdata']."</em>";
								$output .= " <em>".__('Order ID:','evotx')." <a class='evo_btn' href='".admin_url('post.php?post='.$orderID.'&action=edit')."'>".$orderID."</a></em>";
							$output .= "</span>";


							$output .= "</span>";
							$output .= "</span>";

							
							$content[$key][] = $output;
							$indexO++;
						}


						echo "<p class='attendee'>";
						echo "<span class='event_time'>".__('Event Start:','evotx').' '.$event_time."<em>".__('Total','evotx')." {$totalCompleteCount}</em></span>";
						if(!empty($content['good']))	echo implode('', $content['good']);
						echo "<span class='separatation'>".__('Other incompleted orders','evotx')."</span>";
						if(!empty($content['bad'])) echo implode('', $content['bad']);

						echo "</p>";					
					}
					echo "</div>";
					echo "</div>";
				}else{
					echo "<div class='evotx'>";
					echo "<p class='header nada'>".__('Could not find attendees with completed orders.','evotx')."</p>";	
					echo "</div>";
				}
				
				$content = ob_get_clean();
			}
					
			$return_content = array(
				'message'=> $message,
				'status'=>$status,
				'content'=>$content,
			);
			
			echo json_encode($return_content);		
			exit;
		}

	// Download csv list of attendees
		function generate_csv(){

			$e_id = $_REQUEST['e_id'];
			$event = get_post($e_id, ARRAY_A);

			header("Content-type: text/csv");
			header("Content-Disposition: attachment; filename=".$event['post_name']."_".date("d-m-y").".csv");
			header("Pragma: no-cache");
			header("Expires: 0");


			global $evotx;
			$customers = $evotx->functions->get_customer_ticket_list($e_id, $_REQUEST['pid'], 'all');

			if($customers){
				//$fp = fopen('file.csv', 'w');

				$csv_header = apply_filters('evotx_csv_headers',array(
					'Name',
					//'Ticket Holder Name',
					'Email Address',
					'Company',
					'Address',
					'Phone',
					'Ticket IDs',
					'Quantity',
					'Ticket Type',
					'Event Time',
					'Order Status'
				));
				$csv_head = implode(',', $csv_header);
				echo $csv_head."\n";

				$evotx_tix = new evotx_tix();

				$index = 1;
				
				
				// each customer
				foreach($customers as $eventtime=>$cus){

					// each ticket item
					foreach($cus as $ticketItem_){
						
						// Ticket Holder information
							$order_ticket_holders = get_post_meta($ticketItem_['orderid'], '_tixholders', true);
							$order_pmv = get_post_custom($ticketItem_['orderid']);
							$ticket_holder = $evotx->functions->get_ticketholder_names( $e_id,$order_ticket_holders);

						$ticket_numbers = $ticketItem_['tids']; // ticket numbers array with status
						
						// for each ticket ID
						$index = 0;
						foreach($ticket_numbers as $ticket_number=>$_status){					
							$langStatus = $evotx->functions->get_checkin_status($_status);

							$ticket_holder = $evotx_tix->get_ticket_holder_by_ticket_number($ticket_number);

							$name = !$ticket_holder ? $ticketItem_['name']: $ticket_holder;

							$csv_data = apply_filters('evotx_csv_row',array(
								'name'=>$name,
								'email'=>$ticketItem_['email'],
								'company'=> '"'. $ticketItem_['company'] .'"',
								'address'=> $ticketItem_['address'] ,
								'phone'=>$ticketItem_['phone'],
								'ticket_number'=>$ticket_number,
								'qty'=>'1',
								'ticket_type'=>$ticketItem_['type'],
								'event_time'=>'"'.$eventtime.'"',
								'order_status'=>$ticketItem_['order_status']
							), $ticket_number, $e_id, $ticketItem_ , $index, $order_pmv);

							foreach($csv_data as $field=>$val){
								echo $val . ",";
							}

							echo "\n";
							$index++;
						}			
					}
					
				}
			}

		}

	// Email attendee list to someone
		function emailing_attendees_admin(){
			global $evotx, $eventon;

			$eid = $_POST['eid'];
			$wcid = $_POST['wcid'];
			$type = $_POST['type'];
			$RI = !empty($_POST['repeat_interval'])? $_POST['repeat_interval']:'all'; // repeat interval
			$EMAILED = $_message_addition = false;
			$emails = array();

			// email attendees list to someone
			if($type=='someone'){

				// get the emails to send the email to
				$emails = explode(',', str_replace(' ', '', htmlspecialchars_decode($_POST['emails'])));

				$guests = $evotx->functions->get_customer_ticket_list($eid,$wcid, $RI,'customer_order_status');

				if(is_array($guests) && isset($guests['completed']) && count($guests['completed'])>0){
					ob_start();
					
					// get event date time
						$datetime = new evo_datetime();
						$epmv = get_post_custom($eid);
						$eventdate = $datetime->get_correct_formatted_event_repeat_time($epmv, ($RI=='all'?'0':$RI));

					echo "<p>Confirmed Guests for ".get_the_title($eid)." on ".$eventdate['start']."</p>";
					echo "<table style='padding-top:15px; width:100%;text-align:left'><thead><tr>
						<th>Primary Ticket Holder</th>
						<th>Email Address</th>
						<th>Quantity</th>
						<th>Tickets</th>
					</tr></thead>
					<tbody>";
					foreach($guests['completed'] as $guest){
						echo "<tr><td>".$guest['name'] ."</td><td>".$guest['email']."</td><td>".$guest['qty']. "</td>
						<td>";

						foreach($guest['tickets'] as $ticketnumber=>$data){
							echo "<p>";
							echo $ticketnumber .": ". (!empty($data['name'])? $data['name'].' - ':'') . 
								(!empty($data['status'])? $data['status']:'');
							echo "</p>";
						}

						echo "</td></tr>";
					}
					echo "</tbody></table>";
					$_message_addition = ob_get_clean();
				}

				//print_r($_message_addition);

			}elseif($type=='completed'){
				$guests = $evotx->functions->get_customer_ticket_list($eid,$wcid, $RI,'customer_order_status');
				foreach(array('completed') as $order_status){
					if(is_array($guests) && isset($guests[$order_status]) && count($guests[$order_status])>0){
						foreach($guests[$order_status] as $guest){
							$emails[] = $guest['email'];
						}
					}
				}
			}elseif($type=='pending'){
				$guests = $evotx->functions->get_customer_ticket_list($eid,$wcid, $RI,'customer_order_status');
				foreach(array('pending','on-hold') as $order_status){
					if(is_array($guests) && isset($guests[$order_status]) && count($guests[$order_status])>0){
						foreach($guests[$order_status] as $guest){
							$emails[] = $guest['email'];
						}
					}
				}
			}

			// emaling
			if($emails){	
				$email = new evotx_email();			
				$messageBODY = "<div style='padding:15px'>".(!empty($_POST['message'])? strip_tags($_POST['message']).'<br/><br/>':'' ).($_message_addition?$_message_addition:'') . "</div>";
				$messageBODY = $email->get_evo_email_body($messageBODY);
				$from_email = $email->get_from_email_address();

				$args = array(
					'html'=>'yes',
					'to'=> $emails,
					'type'=> ($type=='someone'? '':'bcc'),
					'subject'=>$_POST['subject'],
					'from'=>$from_email,
					'from_name'=> $email->get_from_email_name(),
					'from_email'=> $from_email,
					'message'=>$messageBODY,
				);

				//print_r($args);

				$helper = new evo_helper();
				$EMAILED = $helper->send_email($args);

			}			

			$return_content = array(
				'status'=> ($EMAILED?'0':'did not go'),
				'other'=>$args
			);
			
			echo json_encode($return_content);		
			exit;
		}

	// Resend Ticket Email
	// Used in both evo-tix and order post page
		function admin_resend_confirmation(){
			$order_id = false;
			$status = 'bad';
			$email = '';	

			// get order ID
			$order_id = (!empty($_POST['orderid']))? $_POST['orderid']:false;			
			$ts_mail_errors = array();

			if($order_id){

				// use custom email if passed or else get email to send ticket from order information
				$email = !empty($_POST['email'])? 
					$_POST['email']: 
					get_post_meta($order_id, '_billing_email',true);

				//print_r($email);

				if(!empty($email)){
					$evoemail = new evotx_email();
					$send_mail = $evoemail->send_ticket_email($order_id, false, false, $email);

					if($send_mail) $status = 'good';

					if(!$send_mail){
						global $ts_mail_errors;
						global $phpmailer;

						if (!isset($ts_mail_errors)) $ts_mail_errors = array();

						if (isset($phpmailer)) {
							$ts_mail_errors[] = $phpmailer->ErrorInfo;
						}
					}
				}				
			}	

			// return the results
			$return_content = array(
				'status'=> $status,
				'email'=>$email,
				'errors'=>$ts_mail_errors,
			);
			
			echo json_encode($return_content);		
			exit;
		}

	// get information for a ticket number
		function get_ticektinfo_by_ID(){

			$tickernumber = $_POST['tickernumber'];
			
			$content = $this->get_ticket_info($tickernumber);

			$return_content = array(
				'content'=>$content,
				'status'=> ($content? 'good':'bad'),
			);
			
			echo json_encode($return_content);		
			exit;

		}

		function get_ticket_info($ticket_number){
			if(strpos($ticket_number, '-') === false) return false;

			$tixNum = explode('-', $ticket_number);

			if(!get_post_status($tixNum[0])) return false;

			$tixPMV = get_post_custom($tixNum[0]);

			ob_start();

			$evotx_tix = new evotx_tix();

			$tixPOST = get_post($tixNum[0]);
			$orderStatus = get_post_status($tixPMV['_orderid'][0]);
				$orderStatus = str_replace('wc-', '', $orderStatus);

			$ticketStatus = $evotx_tix->get_ticket_status_by_ticket_number($ticket_number);
			$ticket_holder = $evotx_tix->get_ticket_holder_by_ticket_number($ticket_number);

			echo "<p><em>".__('Ticket Purchased By','evotx').":</em> {$tixPMV['name'][0]}</p>";

			// additional ticket holder associated names
				if(!empty($ticket_holder))
					echo "<p><em>".__('Ticket Holder','evotx').":</em> {$ticket_holder}</p>";

			echo "<p><em>".__('Email Address','evotx').":</em> {$tixPMV['email'][0]}</p>
				<p><em>".__('Event','evotx').":</em> ".get_the_title($tixPMV['_eventid'][0])."</p>
				<p><em>".__('Purchase Date','evotx').":</em> ".$tixPOST->post_date."</p>
				<p><em>".__('Ticket Status','evotx').":</em> <span class='tix_status {$ticketStatus}' data-tiid='{$tixNum[0]}' data-tid='{$ticket_number}' data-status='{$ticketStatus}'>{$ticketStatus}</span></p>
				<p><em>".__('Payment Status','evotx').":</em> {$orderStatus}</p>";

				// other tickets in the same order
				$otherTickets = $evotx_tix->get_other_tix_order($ticket_number);

				if(is_array($otherTickets) && count($otherTickets)>0){
					echo "<div class='evotx_other_tickets'>";
					echo "<p >".__('Other Tickets in the same Order','evotx')."</p>";
					foreach($otherTickets as $num=>$status){
						echo "<p><em>".__('Ticekt Number','evotx').":</em> ".$num."</p>";
						echo "<p style='padding-bottom:10px;'><em>".__('Ticekt Status','evotx').":</em> <span class='tix_status {$status}' data-tiid='{$tixNum[0]}' data-tid='{$num}' data-status='{$status}'>{$status}</span></p>";
					}
					echo "</div>";
				}

			return ob_get_clean();
		}


}
new evotx_admin_ajax();