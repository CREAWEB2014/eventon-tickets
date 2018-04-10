<?php

	// single product add to cart button for woocommerce
	// version : 1.3.9
	
?>

<div class='tx_single'>
<p itemprop="price" class="price tx_price_line">
	<span class='label'><?php echo eventon_get_custom_language($opt, 'evoTX_002ff','Price');?></span>
	<span class='value'><?php echo apply_filters('evotx_single_prod_price', $product->get_price_html(), $object); ?></span>
</p>

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

		<?php if ( ! $product->is_sold_individually() ):?>
		<p class="evotx_quantity">
			<span class='evotx_label'><?php evo_lang_e('How many tickets?');?></span>
			<span class="qty">
				<b class="min evotx_qty_change">-</b>
				<em>1</em>
				<b class="plu evotx_qty_change">+</b>
				<input type="hidden" name='quantity' value='1' max='<?php echo !empty($max_quantity)? $max_quantity:'na';?>'/>
			</span>
		</p>
		<?php endif;?>
	 	<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" />
	 	<button data-product_id='<?php echo $woo_product_id;?>' id='cart_btn' class="evoAddToCart evcal_btn single_add_to_cart_button button alt" data-redirect='<?php echo !empty($ticket_redirect)? $ticket_redirect:'';?>'><?php echo apply_filters(
	 		'single_add_to_cart_text',
	 		eventon_get_custom_language($opt, 'evoTX_002','Add to Cart'), 
	 		$product->get_type(),
	 		$object->event_id
	 	); ?></button>
		<div class="clear"></div>

		<?php do_action('evotx_after_single_addtocart', $woo_product_id, $object->event_id);?>

	 	<div class="clear"></div>
 	</div>
 	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
</form>
<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
</div>