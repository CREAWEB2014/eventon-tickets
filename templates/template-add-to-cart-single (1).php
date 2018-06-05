<?php
/**
 * Single Ticket add to cart section 
 * @updated 1.6.9
 */
?>
<div class='tx_single'>
<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>
<?php

	$max_quantity = ($tix_inStock) ? 
		( is_numeric($tix_inStock)? $tix_inStock:''): 
		($product->backorders_allowed() ? '' : $product->get_stock_quantity());
	
?>

<form class='tx_orderonline_single' data-producttype='single' method="post" enctype='multipart/form-data'>
	<?php do_action( 'woocommerce_before_add_to_cart_button', $woo_product_id ); ?>
	<div class='tx_orderonline_add_cart'>
		
		<?php do_action('evotx_before_single_addtocart', $woo_product_id, $object->event_id);?>
		
		<div class='evotx_hidable_section'>
			<?php
				// Base Price HTML
				$base_price = apply_filters('evotx_single_prod_price', $product->get_price(), $object);
				$tix_helper->base_price_html($base_price);
			?>
		
			<?php if ( ! $product->is_sold_individually() ): ?>
				<?php $tix_helper->ticket_qty_html( (!empty($max_quantity)? $max_quantity:'na') );	?>
			<?php endif;?>
		
			<?php $tix_helper->total_price_html( $base_price, '', $woo_product_id);?>
			
		 	<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />
		 	
		 	<?php $tix_helper->add_to_cart_btn_html('evoAddToCart single_add_to_cart_button button alt', array(
		 		'product_id'=>$woo_product_id,
		 		'l'=>EVO()->lang
		 	));?>
					
			<?php 
			// show remaining tickets
				if($event->is_show_remaining_stock()):
					$tix_helper->remaining_stock_html($tix_inStock, $this->langX('Tickets remaining!', 'evoTX_013') );
				endif;
			?>
			<?php $tix_helper->print_add_to_cart_data();?>
		
		</div>

		<?php $tix_helper->__get_addtocart_msg_footer();?>
	 	
		
		<?php do_action('evotx_after_single_addtocart', $woo_product_id, $object->event_id);?>

	 	<div class="clear"></div>
 	</div>
 	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>
<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
</div>