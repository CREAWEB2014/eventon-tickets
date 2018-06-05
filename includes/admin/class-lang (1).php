<?php
/**
 * All the language string additions
 */

class evotx_lang{
	function __construct(){
		add_filter('eventon_settings_lang_tab_content', array($this,'evotx_language_additions'), 10, 1);
	}
	function evotx_language_additions($_existen){
		$new_ar = array(
			array('type'=>'togheader','name'=>'ADDON: Event Tickets'),
				array('label'=>'Ticket Statuses','type'=>'subheader'),
					array('label'=>'Ticket Status: Check-in','name'=>'evoTX_003x',),
					array('label'=>'Ticket Status: Checked','name'=>'evoTX_003y',),
					array('label'=>'Refunded','var'=>1),
				array('type'=>'togend'),

				array('label'=>'Ticket section title', 'name'=>'evoTX_001', 'legend'=>''),	
				array('label'=>'Add to Cart', 'name'=>'evoTX_002', 'legend'=>''),
				array('label'=>'Order Now', 'name'=>'evoTX_002ee', 'legend'=>''),
				array('label'=>'Price', 'name'=>'evoTX_002ff', 'legend'=>''),
				array('label'=>'This option is sold out!', 'var'=>1),
				array('label'=>'How many tickets?', 'var'=>1),
				array('label'=>'Clear selection', 'var'=>1),
				array('label'=>'Next Available Event', 'var'=>1),

				array('label'=>'Successfully Added to Cart!', 'name'=>'evoTX_009'),
				array('label'=>'You can not buy more than available tickets, please try again!', 'name'=>'evoTX_009a'),
				array('label'=>'Checkout', 'var'=>1 ),
				array('label'=>'View Cart', 'var'=>1 ),
				array('label'=>'Added to cart', 'var'=>1 ),
				array('label'=>'Sold Out!', 'name'=>'evoTX_012', 'legend'=>'Out of stock for tickets'),
				array('label'=>'Event Over', 'name'=>'evoTX_012b', 'legend'=>'When events are past'),
				array('label'=>'Tickets are not available for sale any more for this event!', 'name'=>'evoTX_012a', ),
				array('label'=>'You must login to buy tickets!','var'=>1),
				array('label'=>'Login Now','var'=>1),
				array('label'=>'Tickets remaining!', 'name'=>'evoTX_013',),
				array('label'=>'Your event Tickets', 'name'=>'evoTX_014',),
				array('label'=>'Guest List', 'var'=>'1'),
				array('label'=>'Attending', 'var'=>'1'),
				array('label'=>'Event Name', 'var'=>'1'),
				array('label'=>'Full Name of the Ticket Holder', 'var'=>'1'),
				array('label'=>'Full Name', 'var'=>1),
				array('label'=>'Additional Ticket Information', 'var'=>'1'),
				array('label'=>'Once the order is processed your event tickets will show here!', 'var'=>1),
				array('label'=>'This order has been refunded!', 'var'=>1),

				array('label'=>'Ticket Inquiries Front-end Form','type'=>'subheader'),
					array('label'=>'Inquire before buy','name'=>'evoTX_inq_01','legend'=>''),
					array('label'=>'Your Name','name'=>'evoTX_inq_02','legend'=>''),
					array('label'=>'Email Address','name'=>'evoTX_inq_03','legend'=>''),
					array('label'=>'Question','name'=>'evoTX_inq_04'),
					array('label'=>'Phone Number','name'=>'evoTX_inq_04a'),
					array('label'=>'**All Fields are Required','name'=>'evoTX_inq_05','legend'=>''),
					array('label'=>'Verify Your Inquiry','name'=>'evoTX_inq_02a','legend'=>''),
					array('label'=>'Required Fields are Missing, Please Try Again!','name'=>'evoTX_inq_06','legend'=>''),
					array('label'=>'Submit','name'=>'evoTX_inq_07','legend'=>''),
					array('label'=>'GOT IT! -- We will get back to you as soon as we can.','name'=>'evoTX_inq_08','legend'=>''),					
				array('type'=>'togend'),

				array('label'=>'Ticket Email','type'=>'subheader'),
					array('label'=>'Ticket #', 'name'=>'evoTX_003', 'legend'=>''),
					array('label'=>'Primary Ticket Holder', 'name'=>'evoTX_004', 'legend'=>''),
					array('label'=>'Quantity', 'name'=>'evoTX_005', 'legend'=>''),
					array('label'=>'Qty', 'name'=>'evoTX_005b', ),						
					array('label'=>'Ticket Type', 'name'=>'evoTX_006'),
					array('label'=>'We look forward to seeing you!', 'name'=>'evoTX_007', 'legend'=>''),
					array('label'=>'Contact us for questions and concerns', 'name'=>'evoTX_008', 'legend'=>''),
					array('label'=>'Your Ticket For','name'=>'evotxem_001'),
					array('label'=>'Date','name'=>'evotxem_002'),
					array('label'=>'Ticket Number','name'=>'evotxem_003'),
				array('type'=>'togend'),
				array('label'=>'Other Strings','type'=>'subheader'),
					array('label'=>'Event Name', 'name'=>'evoTX_005d'),
					array('label'=>'Event Time', 'name'=>'evoTX_005a'),
					array('label'=>'Event Location', 'name'=>'evoTX_005c'),
				array('type'=>'togend'),
			array('type'=>'togend'),
		);
		return (is_array($_existen))? array_merge($_existen, $new_ar): $_existen;
	}
}