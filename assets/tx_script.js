/**
 * Event Ticket script 
 * @version 1.6
 */
jQuery(document).ready(function($){
	
// on change variable product selection
    $('body').on('change','table.variations select',function(){
        CART = $(this).closest('table').siblings('.evotx_orderonline_add_cart');
        STOCK = CART.find('p.stock');

        // check if variable products are out of stock
        if(STOCK.hasClass('out-of-stock')){
            CART.find('.variations_button').hide();
        }else{
            CART.find('.variations_button').show();
        }
    });


// increase and reduce quantity
    $('body').on('click','.evotx_qty_change', function(event){

        OBJ = $(this);

        if(OBJ.closest('.evotx_quantity').hasClass('one')) return;

        QTY = parseInt(OBJ.siblings('em').html());
        MAX = OBJ.siblings('input').data('max');        
        if(!MAX) MAX = OBJ.siblings('input').attr('max');
           

        NEWQTY = (OBJ.hasClass('plu'))?  QTY+1: QTY-1;

        NEWQTY =(NEWQTY <= 0)? 0: NEWQTY;
        NEWQTY = (MAX!='' && NEWQTY > MAX)? MAX: NEWQTY;

        OBJ.siblings('em').html(NEWQTY);
        OBJ.siblings('input').val(NEWQTY);

        if( QTY != NEWQTY) $('body').trigger('evotx_qty_changed',[NEWQTY, MAX, OBJ]);
       

        if(NEWQTY == MAX){
            
            PLU = OBJ.parent().find('b.plu');
            if(!PLU.hasClass('reached')) PLU.addClass('reached');   

            if(QTY == MAX)   $('body').trigger('evotx_qty_max_reached',[NEWQTY, MAX, OBJ]);                 
        }else{            
            OBJ.parent().find('b.plu').removeClass('reached');
        } 
    });

// on triggers for variations form
    $('body').on('reset_data','form.evotx_orderonline_variable',function(event){
        FORM = $(this);     
        FORM.find('.evotx_variation_purchase_section').hide();
    });
    $('body').on('show_variation','form.evotx_orderonline_variable',function(event, variation, purchasable){
        FORM = $(this);    
        
        // variation not in stock
        if(!variation.is_in_stock){
            FORM.find('.evotx_variations_soldout').show();
            FORM.find('.evotx_variation_purchase_section').hide();
        }else{
            FORM.find('.evotx_variations_soldout').hide();
            FORM.find('.evotx_variation_purchase_section').show();
        }

        if(variation.sold_individually){
            FORM.find('.evotx_quantity').hide();
        }

        NEWQTY = parseInt(FORM.find('.evotx_quantity_adjuster em').html());
        NEWQTY = (variation.max_qty!= '' && NEWQTY > variation.max_qty)? variation.max_qty: NEWQTY;

        FORM.find('.evotx_quantity_adjuster em').html( NEWQTY);
        FORM.find('.evotx_quantity_adjuster input').val( NEWQTY);
    });
    
// click add to cart for variable product
    $('body').on('click','.evoAddToCart', function(e){

        e.preventDefault();
        thisButton = $(this);

        // loading animation
        thisButton.closest('.evoTX_wc').addClass('evoloading');

        // Initial
            TICKET_ROW = thisButton.closest('.evo_metarow_tix');
            NOTICE_field = TICKET_ROW.find('.tx_wc_notic');
            PURCHASESEC = TICKET_ROW.find('.evoTX_wc');

        // reset
            NOTICE_field.removeClass('error').hide();

        // set cart item additional data
            var ticket_row = thisButton.closest('.evo_metarow_tix');
            var event_id = ticket_row.attr('data-event_id');
            var ri = ticket_row.attr('data-ri');
            var event_location = thisButton.closest('.evcal_eventcard').find('.evo_location_name').html();
           
            event_location = (event_location !== undefined && event_location != '' )? 
                encodeURIComponent(event_location):'';

            //console.log(event_location);
            
            // variable item
                if(thisButton.hasClass('variable_add_to_cart_button')){

                    var variation_form = thisButton.closest('form.variations_form'),
                        variations_table = variation_form.find('table.variations'),
                        singleVariation = variation_form.find('.single_variation p.stock');

                        // Stop processing is out of stock
                        if(singleVariation.hasClass('out-of-stock')){
                            return;
                        }

                    var product_id = parseInt(variation_form.attr('data-product_id'));
                    var variation_id = parseInt(variation_form.find('input[name=variation_id]').val());
                    var quantity = parseInt(variation_form.find('input[name=quantity]').val());

                    quantity = (quantity=== undefined || quantity == '' || isNaN(quantity)) ? 1: quantity;

                    values = variation_form.serialize();

                    var attributes ='';
                    variations_table.find('select').each(function(index){
                        attributes += '&'+ $(this).attr('name') +'='+ $(this).val();
                    });

                    // get data from the add to cart form
                    dataform = thisButton.closest('.variations_form').serializeArray();
                    var data_arg = dataform;

                    $.ajax({
                        type: 'POST',data: data_arg,
                        url: '?add-to-cart='+product_id+'&variation_id='+variation_id+attributes+'&quantity='+quantity +'&ri='+ri+'&eid='+event_id +'&eloc='+event_location,
                        beforeSend: function(){
                            $('body').trigger('adding_to_cart');
                        },
                        success: function(response, textStatus, jqXHR){
                            // Show success message
                            NOTICE_field.fadeIn();
                            PURCHASESEC.hide();
                        }, complete: function(){
                            thisButton.closest('.evoTX_wc').removeClass('evoloading');

                            // if need to be redirected to cart after adding
                                if(evotx_object.redirect_to_cart == 'cart'){
                                    window.location.href = evotx_object.cart_url;
                                }else if( evotx_object.redirect_to_cart =='checkout'){                                    
                                    window.location.href = evotx_object.checkout_url;
                                }else{
                                    update_wc_cart();
                                }                        
                        }
                    }); 
                }

            // simple item
            if(thisButton.hasClass('single_add_to_cart_button')){
                // /console.log('66');
                
                TICKET_section = thisButton.closest('.evoTX_wc');
                QTY_field = TICKET_section.find('input[name=quantity]');
                
                var sold_individually = TICKET_section.data('si');
                var qty = (sold_individually=='yes')? 1: QTY_field.val();
                var product_id = thisButton.attr('data-product_id');
                MAX_qty = QTY_field.attr('max');

                //console.log(MAX_qty+' '+qty);

                // check if max quantity is not exceeded
                if( MAX_qty != '' && parseInt(MAX_qty) < qty){
                    NOTICE_field.addClass('error').show();
                    thisButton.closest('.evoTX_wc').removeClass('evoloading');
                }else{

                    // get data from the add to cart form
                    dataform = thisButton.closest('.tx_orderonline_single').serializeArray();
                    var data_arg = dataform;

                    // passing location values
                    location_str = event_location!= ''? '&eloc='+event_location: '';

                    $.ajax({
                        type: 'POST',
                        data: data_arg,
                        url: '?add-to-cart='+product_id+'&quantity='+qty +'&ri='+ri+'&eid='+event_id + location_str,
                        beforeSend: function(){
                            $('body').trigger('adding_to_cart');
                        },
                        success: function(response, textStatus, jqXHR){

                            // Show success message
                            NOTICE_field.fadeIn();
                            PURCHASESEC.hide();

                        }, complete: function(){
                            thisButton.closest('.evoTX_wc').removeClass('evoloading');

                            // reduce remaining qty
                                var remainingEL = thisButton.closest('.evcal_evdata_cell').find('.evotx_remaining');
                                var remaining_count = parseInt(remainingEL.attr('data-count'));
                                //console.log(remaining_count);
                                if(remaining_count){
                                	var new_count = remaining_count-qty;
                                    new_count = new_count<0? 0: new_count;
                                   
                                    // update
                                        remainingEL.attr({'data-count':new_count}).find('span').html(new_count);
                                       	// change input field max value
                                       		thisButton.siblings('.quantity').find('input.qty').attr('max',new_count);

                                        // hide if no tickets left
                                        if(new_count==0)    $(this).fadeOut();
                                }
                            // if need to be redirected to cart after adding
                                if(evotx_object.redirect_to_cart == 'cart'){
                                    window.location.href = evotx_object.cart_url;
                                }else if( evotx_object.redirect_to_cart =='checkout'){                                    
                                    window.location.href = evotx_object.checkout_url;
                                }else{
                                    update_wc_cart();
                                } 
                        }   
                    });
                     
                }
            }
        
        return false;
    });

// Update mini cart content
    function update_wc_cart(){
        var data = {
            action: 'evoTX_ajax_09'
        };
        $.ajax({
            type:'POST',url:evotx_object.ajaxurl,
            data:data,
            dataType:'json',
            success:function(data){
                
                if (!data) return;

                var this_page = window.location.toString();
                this_page = this_page.replace( 'add-to-cart', 'added-to-cart' );

                var fragments = data.fragments;
                var cart_hash = data.cart_hash;

                // Block fragments class
                fragments && $.each(fragments, function (key, value) {
                    $(key).addClass('updating');
                });
                 
                // Block fragments class
                    if ( fragments ) {
                        $.each( fragments, function( key ) {
                            $( key ).addClass( 'updating' );
                        });
                    }   

                // Block widgets and fragments
                    $( '.shop_table.cart, .updating, .cart_totals' )
                        .fadeTo( '400', '0.6' )
                        .block({
                            message: null,
                            overlayCSS: {
                                opacity: 0.6
                            }
                    });           
                 
                // Replace fragments
                    if ( fragments ) {
                        $.each( fragments, function( key, value ) {
                            $( key ).replaceWith( value );
                        });

                        $( document.body ).trigger( 'wc_fragments_loaded' );            
                    }
                 
                // Unblock
                $( '.widget_shopping_cart, .updating' ).stop( true ).css( 'opacity', '1' ).unblock();
                 
                // Cart page elements
                $( '.shop_table.cart' ).load( this_page + ' .shop_table.cart:eq(0) > *', function() {

                    $( '.shop_table.cart' ).stop( true ).css( 'opacity', '1' ).unblock();

                    $( document.body ).trigger( 'cart_page_refreshed' );
                });

                $( '.cart_totals' ).load( this_page + ' .cart_totals:eq(0) > *', function() {
                    $( '.cart_totals' ).stop( true ).css( 'opacity', '1' ).unblock();
                });
                 
                // Trigger event so themes can refresh other areas
                $( document.body ).trigger( 'added_to_cart', [ fragments, cart_hash ] );
            }
        });
    }

// inquiry submissions
    $('body').on('click','.evotx_INQ_btn', function(){
        $(this).siblings('.evotxINQ_box').slideToggle();
    });
    $('body').on('click','.evotx_INQ_submit', function(event){
        event.preventDefault;
        var form = $(this).closest('.evotxINQ_form');
        var notif = form.find('.notif');

        //reset 
        	form.find('.evotxinq_field').removeClass('error');

        //reset notification
        notif.html( notif.attr('data-notif') );

        var data = {
            action: 'evoTX_ajax_06',
            event_id: form.attr('data-event_id'),
            ri: form.attr('data-ri'),
        };

        error = 'none';
        form.find('.evotxinq_field').each(function(index){
            if( $(this).val()==''){
            	error='yes';
            	$(this).addClass('error');
            } 
            data[$(this).attr('name')] = $(this).val();
        });

        // validate captcha
        var human = validate_human( form.find('input.captcha') );
		if(!human){
			form.find('input.captcha').addClass('error');
			error=3;
		}

        if(error=='none'){
            $.ajax({
                type:'POST',url:evotx_object.ajaxurl,
                data:data,
                beforeSend: function(){
                    form.addClass('loading');
                },success:function(data){
                    form.slideUp();
                    form.siblings('.evotxINQ_msg').fadeIn(function(){
                        form.removeClass('loading');
                    });
                }
            });
        }else{
            notif.html( form.attr('data-err') );
        }
    });
	// validate humans
		function validate_human(field){
			if(field==undefined){
				return true;
			}else{
				var numbers = ['11', '3', '6', '3', '8'];
				if(numbers[field.attr('data-cal')] == field.val() ){
					return true;
				}else{ return false;}
			}				
		}

// add to cart button from eventtop
     $('body').on('click','.evotx_add_to_cart em', function(){   });

    // hover over guests list icons
        $('body').on('mouseover','.evotx_whos_coming span', function(){
            name = $(this).attr('data-name');
            html = $(this).html();
            $(this).html(name).attr('data-intials', html).addClass('hover');
        });
        $('body').on('mouseout','.evotx_whos_coming span', function(){
            $(this).html( $(this).attr('data-intials')).removeClass('hover');
        });
	
// ActionUser event manager
    // show rsvp stats for events
        $('#evoau_event_manager').on('click','a.load_tix_stats',function(event){
            event.preventDefault();
            MANAGER = $(this).closest('.evoau_manager');
            var data_arg = {
                action: 'evotx_ajax_get_auem_stats',
                eid: $(this).data('eid')
            };
            $.ajax({
                beforeSend: function(){
                    MANAGER.find('.eventon_actionuser_eventslist').addClass('evoloading');
                },
                type: 'POST',
                url:evotx_object.ajaxurl,
                data: data_arg,
                dataType:'json',
                success:function(data){
                    $('body').trigger('evoau_show_eventdata',[MANAGER, data.html, true]);
                },complete:function(){ 
                    MANAGER.find('.eventon_actionuser_eventslist').removeClass('evoloading');
                }
            });
        });

    // check in attendees
        $('.evoau_manager_event').on('click','.evotx_status', function(){
            var obj = $(this);

            if( obj.parent().hasClass('chkb') ){

                var data_arg = {
                    action: 'the_ajax_evotx_a5',
                    tid: obj.attr('data-tid'),
                    tiid: obj.attr('data-tiid'),
                    status: obj.attr('data-status'),
                };
                $.ajax({
                    beforeSend: function(){    obj.html( obj.html()+'...' );  },
                    type: 'POST',
                    url:evotx_object.ajaxurl,
                    data: data_arg,
                    dataType:'json',
                    success:function(data){
                        //alert(data);
                        obj.attr({'data-status':data.new_status}).html(data.new_status_lang).removeAttr('class').addClass('evotx_status '+ data.new_status);

                    }
                });
            }
        });
    // open incompleted orders
        $('.evoau_manager_event_content').on('click','span.evotx_incomplete_orders',function(){
            $(this).closest('table').find('td.hidden').toggleClass('bad');
        });
});