<?php
/**
 * Event Ticket Custom Post class
 *
 * @author 		AJDE
 * @category 	Admin
 * @package 	eventON/Admin/evo-tix
 * @version     1.2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class evotx_tix_cpt{	
	// Constructor
		function __construct(){
			add_filter( 'request', array($this,'ticket_order') );

			add_filter( 'manage_edit-evo-tix_sortable_columns', array($this,'ticket_sort') );
			add_action('manage_evo-tix_posts_custom_column', array($this,'evo_tx_custom_event_columns'), 2 );
			add_filter( 'manage_edit-evo-tix_columns', array($this,'evo_tx_edit_event_columns') );
			add_action("admin_init", array($this,"_evo_tx_remove_box"));

			// woocommerce Orders columns
		    $posttype = "shop_order";
		    add_filter( "manage_edit-{$posttype}_columns", array($this, 'SO_edit_columns'), 20, 1 );
		    add_action( "manage_{$posttype}_posts_custom_column", array($this, 'column_display_so_22237380'), 20, 2 ); 
		    add_filter( "manage_edit-{$posttype}_sortable_columns", array($this, 'column_sort_so_22237380') );
			
			// add woo into event CPT columns 
			add_filter('evo_event_columns', array($this, 'add_column_title'), 10, 1);
			add_filter('evo_column_type_woo', array($this, 'column_content'), 10, 1);
		}

	// add order type columns
		function SO_edit_columns( $columns ){
		    $columns['order_type'] = "Type";
		    return $columns;
		}
		function column_display_so_22237380( $column_name, $post_id ) {
		    if ( 'order_type' != $column_name )
		        return;

		    $order_type_ = get_post_meta($post_id, '_order_type', true);
		    $order_type = (!empty($order_type_) && $order_type_== 'evotix')? 'Ticket Order':'None Ticket Order';
		    if ( $order_type ){
		        echo $order_type;
		    }
		}
		function column_sort_so_22237380( $columns ) {
		    $columns['order_type'] = 'order_type';
		    return $columns;
		}

	// remove the main editor box
		function _evo_tx_remove_box(){
			remove_post_type_support('evo-tix', 'title');
			remove_post_type_support('evo-tix', 'editor');
		}

	// add new column to menu items
			function add_column_title($columns){
				$columns['woo']= '<i title="Connected to woocommerce">'.__('Tickets','evotx').'</i>';
				return $columns;
			}
			function column_content($post_id){				
				$evotx_tix = get_post_meta($post_id, 'evotx_tix', true);

				if(!empty($evotx_tix) && $evotx_tix=='yes'){
					global $evotx_admin;

					$__woo = get_post_meta($post_id, 'tx_woocommerce_product_id', true);
					//$__wo_perma = (!empty($__woo))? get_edit_post_link($__woo):null;
					
					$product_type = 'simple';
					$product_type = $evotx_admin->get_product_type($__woo);

					return (!empty($__woo))?
						"<span class='yeswootix' title='".apply_filters('evotx_admin_events_column_title',$product_type, $post_id)."'><b></b></span>":
						"<span class='nowootix'>".__('No','evotx') . "</span>";
				}else{
					return "<span class='nowootix'>".__('No','evotx') . '</span>';
				}
			}

	/**
	 * Define custom columns for evo-tix
	 * @param  array $existing_columns
	 * @return array
	 */
		function evo_tx_edit_event_columns( $existing_columns ) {
			global $eventon;
			
			// GET event type custom names
			
			if ( empty( $existing_columns ) && ! is_array( $existing_columns ) )
				$existing_columns = array();
			if($_GET['post_type']!='evo-tix')
				return;

			unset( $existing_columns['title'], $existing_columns['comments'], $existing_columns['date'] );

			$columns = array();
			$columns["cb"] = "<input type=\"checkbox\" />";	

			$columns['tix'] = __( 'Event Ticket(s)', 'evotx' );
			$columns['tix_status'] = __( 'Status', 'evotx' );
			$columns['tix_wcid'] = __( 'Order ID', 'evotx' );
			
			$columns["tix_event"] = __( 'Event', 'evotx' );
			$columns["tix_type"] = __( 'Ticket Type', 'evotx' );
			$columns["date"] = __( 'Date', 'evotx' );				
			

			return array_merge( $columns, $existing_columns );
		}		

	// field values
		function evo_tx_custom_event_columns( $column ) {
			global $post, $eventon, $evotx;

			$meta = get_post_meta($post->ID); // ticket item meta
			
			$evotx_tix = $ET = new evotx_tix();
			$ET->evo_tix_id = $post->ID;



			switch ($column) {	
				case 'tix_wcid':
					$wcid = $ET->get_prop('_orderid');
					echo '<a class="row-title" href="'.get_edit_post_link( $wcid ).'">' . $wcid.'</a>';
				break;
				case "tix":
					// new method 1.7
					if( $ET->get_prop('_ticket_number') ){
						echo "<strong><a class='row-title' href='". get_edit_post_link( $post->ID ) ."'>#".$ET->get_prop('_ticket_number')."</a></strong> by ".$ET->get_prop('name')." ".$ET->get_prop('email');
						echo "</span>";
					}else{
						$edit_link = get_edit_post_link( $post->ID );
						$cost = $ET->get_prop('cost');

						echo "<strong><a class='row-title' href='".$edit_link."'>#{$post->ID}</a></strong> by ".$meta['name'][0]." ".$meta['email'][0];

						// get ticket ids
						$tix_id_ar = $evotx_tix->get_ticket_numbers_by_evotix($post->ID, 'string');

						echo '<br/><em class="lite">Ticket ID(s):</em> <i>'.$tix_id_ar.'</i>';

						echo '<br/><span class="evotx_intrim">'. $ET->get_prop('qty') .' <em class="lite">(Qty)</em> - '. ((!empty($cost))? get_woocommerce_currency_symbol().apply_filters('woocommerce_get_price', $cost): '-').'<em class="lite"> (Total)</em></span>';
					}
					
				break;
				case "tix_event":
					$e_id = (!empty($meta['_eventid']))? $meta['_eventid'][0]: null;

					if($e_id){
						echo '<strong><a class="row-title" href="'.get_edit_post_link( $e_id ).'">' . get_the_title($e_id).'</a></strong>';
					}else{ echo '--';}

				break;
				case "tix_type":
					$type = get_post_meta($post->ID, 'type', true);						
					echo (!empty($type))? $type: '-';
				break;
				
				case "tix_status":
					// order
						$order_id = $ET->get_prop('_orderid');
						$order_status = 'n/a';							
						if($order_id){	
							$order = new WC_Order( $order_id );
							$order_status = $order->get_status();
						}

					// new method 1.7
					if( $tn= $ET->get_prop('_ticket_number') ){
						$tickets = $ET->get_prop('ticket_ids');
						$this_ticket_status = isset($tickets[$tn])? $tickets[$tn]: $ET->get_prop('status');

						$display = $_checked_class = $this_ticket_status;
					}else{
						$checked_count = $evotx_tix->checked_count($post->ID);
						$status = 'checked';

						$checked_count_ = !empty($checked_count['checked'])? $checked_count['checked']:'0';
						
						// if all checked 
							$_checked_class = ($checked_count_ == $checked_count['qty'])? 'checked':'check-in';

						// different state on checked tickets
							if($checked_count['qty'] == '1' && $checked_count_=='0' ){
								$display = $evotx_tix->get_checkin_status_text('check-in');
							}elseif(($checked_count['qty'] == '1' && $checked_count_=='1')|| ($checked_count['qty']>1 && $checked_count['qty'] == $checked_count_)){
								$display = $evotx_tix->get_checkin_status_text('checked');
							}else{
								$display = $evotx_tix->get_checkin_status_text($status).' '.$checked_count_.'/'.$checked_count['qty'];
							}						
					}					

					echo "<p class='evotx_status_list {$order_status}'><em class='lite'>".__('Order','evotx').":</em> <span class='evotx_wcorderstatus {$order_status}'>".$order_status ."</span></p>";

					if( $order_status == 'completed'){
						echo "<p class='evotx_status_list {$_checked_class}'><em class='lite'>".__('Ticket','evotx').":</em> <span class='evotx_status {$_checked_class}'>".$display."</span></p>";	
					}	

				break;
			}
		}
	
	// make ticket columns sortable
		function ticket_sort($columns) {
			$custom = array(
				'event'		=> 'event',
			);
			return wp_parse_args( $custom, $columns );
		}
		function ticket_order( $vars ) {
			if (isset( $vars['orderby'] )) :
				if ( 'event' == $vars['orderby'] ) :
					$vars = array_merge( $vars, array(
						'meta_key' 	=> '_eventid',
						'orderby' 	=> 'meta_value'
					) );
				endif;
				
			endif;

			return $vars;
		}
}
new evotx_tix_cpt();
