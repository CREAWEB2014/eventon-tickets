<?php
/**
 * Ticket Addon Helpers for ticket addon extensions
 * @version 0.1
 */

class evotx_helper{
	 
	function convert_to_currency($price, $symbol = true){		

		extract( apply_filters( 'wc_price_args', wp_parse_args( array(), array(
	        'ex_tax_label'       => false,
	        'currency'           => '',
	        'decimal_separator'  => wc_get_price_decimal_separator(),
	        'thousand_separator' => wc_get_price_thousand_separator(),
	        'decimals'           => wc_get_price_decimals(),
	        'price_format'       => get_woocommerce_price_format(),
	    ) ) ) );

		$negative = $price < 0;
		$price = floatval($negative? $price *-1: $price);
		$price = apply_filters( 'formatted_woocommerce_price', number_format( $price, $decimals, $decimal_separator, $thousand_separator ), $price, $decimals, $decimal_separator, $thousand_separator );

		$sym = $symbol? get_woocommerce_currency_symbol($currency):'';

		if ( apply_filters( 'woocommerce_price_trim_zeros', false ) && $decimals > 0 ) {
	        $price = wc_trim_zeros( $price );
	    }

	    $return = ( $negative ? '-' : '' ) . sprintf( $price_format, $sym, $price );

	    if ( $ex_tax_label && wc_tax_enabled() ) {
	        $return .= ' <small class="woocommerce-Price-taxLabel tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
	    }

		return $return;
	}

	function add_to_cart_html(){
		ob_start();?>
		<p class='evotx_success_msg'><b><?php evo_lang_e('Added to cart');?>!</b>
		<span>
			<a class='evcal_btn' href="<?php echo wc_get_cart_url();?>"><?php evo_lang_e('View Cart');?></a> <em>|</em>
			<a class='evcal_btn' href="<?php echo wc_get_checkout_url();?>"><?php evo_lang_e('Checkout');?></a></span>
		</p>
		<?php
		return ob_get_clean();
	}	
}