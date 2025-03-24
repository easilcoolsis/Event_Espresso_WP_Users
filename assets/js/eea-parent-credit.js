var CREDIT;
jQuery(document).ready(function($) {

    /**
     * @namespace CREDIT
     * @type {{
		 *     container: object,
		 *     form_input: object,
		 *     form_data: object,
		 *     parent_credit: object,
		 *     display_debug: number,
	 * }}
     * @namespace form_data
     * @type {{
		 *     action: string,
		 *     parent_credit: string,
		 *     noheader: boolean,
		 *     ee_front_ajax: boolean,
		 *     EESID: string,
	 * }}
     * @namespace eei18n
     * @type {{
		 *     EESID: string,
		 *     ajax_url: string,
		 *     wp_debug: boolean,
		 *     no_promotions_code: string
		 * }}
     * @namespace response
     * @type {{
		 *     errors: string,
		 *     attention: string,
		 *     success: string,
		 *     return_data: object,
		 *     payment_info: string,
		 *     promo_accepted: boolean
		 * }}
     * @namespace return_data
     * @type {{
		 *     payment_info: string,
		 *     cart_total: number
		 * }}
     */
    CREDIT = {

        // main parent credit container
        container:     {},
        // parent credit text input label
        form_label:    {},
        // parent credit text input field
        form_input:    {},
        // parent credit submit button
        form_submit:   {},
        // array of form data
        form_data:     {},
        // array of input fields that require values
        parent_credit: {},
        // display debugging info in console?
        display_debug: eei18n.wp_debug,

        /********** INITIAL SETUP **********/



        /**
         * @function
         */
        initialize: function() {
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.initialize()', null, 4));
            }
            var container = $('#ee-spco-payment_options-reg-step-form-payment-options-before-payment-options');
            if (container.length) {
                CREDIT.container = container;
                CREDIT.adjust_input_and_submit_button_css();
                CREDIT.set_listener_for_form_input();
            }
        },

        /**
         * @function
         */
        adjust_input_and_submit_button_css: function() {
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.adjust_input_and_submit_button_css()', null, 4));
            }
            CREDIT.form_label     = $('#ees-parent-credit-input-lbl');
            CREDIT.form_input     = $('#ees-parent-credit-input');
            CREDIT.form_submit    = $('#ees-parent-credit-submit');
            var submit_width     = CREDIT.form_submit.outerWidth();
            var half_label_width = CREDIT.form_label.outerWidth() / 2;
            if (half_label_width > submit_width && half_label_width > 100) {
                var form_label = CREDIT.form_label.position();
                CREDIT.form_input.addClass('ee-credit-combo-input').css({
                    'width':  (CREDIT.container.outerWidth() - submit_width),
                    'height': CREDIT.form_submit.outerHeight(),
                });
                CREDIT.form_submit.addClass('ees-parent-credit-combo-submit').css({'top': form_label.top + CREDIT.form_label.height()});
            }
        },

        /**
         * @function
         */
        set_listener_for_form_input: function() {
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.set_listener_for_form_input()', null, 4));
            }
            const buttonCredit = document.getElementById("ees-parent-credit-submit");
            if (buttonCredit.disabled == true)
			{
            	const buttonPromotion = document.getElementById("ee-promotion-code-submit");
            	buttonPromotion.disabled = true;
			}
            CREDIT.container.on('click', '#ees-parent-credit-submit', function(event) {
                if (CREDIT.display_debug) {
                    console.log(JSON.stringify('>> CLICK << on #ees-parent-credit-submit', null, 4));
                }
                event.preventDefault();
                event.stopPropagation();
                var parent_credit = CREDIT.form_input.val();
                if (typeof parent_credit !== 'undefined' && parent_credit !== '') {
                    if (CREDIT.display_debug) {
                        console.log(JSON.stringify('parent_credit: ' + parent_credit, null, 4));
                    }
                    CREDIT.submit_parent_credit(parent_credit);
                }
            });
        },

        /**
         *  @function
         *  @param {string} parent_credit
         */
        submit_parent_credit: function(parent_credit) {
            // no code ?
            if (parent_credit === '') {
                return;
            }
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.submit_parent_credit()', null, 4));
            }
            CREDIT.form_data            = {};
            CREDIT.form_data.action     = 'submit_parent_credit';
            CREDIT.form_data.parent_credit = parent_credit;
        
            CREDIT.submit_ajax_request();
        },

        /**
         * @function
         * @param  {object} response
         */
        update_payment_info_table: function(response) {
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.update_payment_info_table()', null, 4));
            }
            var $spco_payment_info_table = $('#spco-payment-info-table');
            $spco_payment_info_table.find('tbody').html(response.return_data.payment_info);
            SPCO.scroll_to_top_and_display_messages(SPCO.main_container, response, true);
            if (typeof response.return_data.cart_total !== 'undefined') {
                var payment_amount = parseFloat(response.return_data.cart_total);
                SPCO.main_container.trigger('spco_payment_amount', [payment_amount]);
                if (payment_amount === 0) {
                    var $next_step_btn = $spco_payment_info_table.closest('form').find('.spco-next-step-btn');
                    if (CREDIT.display_debug) {
                        console.log(JSON.stringify('payment_amount === 0', null, 4));
                        console.log(JSON.stringify('> trigger click on #' + $next_step_btn.attr('id'), null, 4));
                    }
                    SPCO.enable_submit_buttons();
                    $next_step_btn.trigger('click');
                }
            }
        },

        /**
         *  @function
         */
        submit_ajax_request: function() {
            // no form_data ?
            if (typeof CREDIT.form_data.action === 'undefined' || CREDIT.form_data.action === '') {
                return;
            }
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.submit_ajax_request()', null, 4));
            }
            
            CREDIT.form_data.action        = CREDIT.form_data.action;
            CREDIT.form_data.noheader      = 1;
            CREDIT.form_data.ee_front_ajax = 1;
            CREDIT.form_data.EESID         = eei18n.EESID;

            if (CREDIT.display_debug) {
                SPCO.console_log_object('CREDIT.form_data', CREDIT.form_data, 0);
            }

            // send AJAX
            $.ajax({
                type:       'POST',
                url:        eei18n.ajax_url,
                data:       CREDIT.form_data,
                dataType:   'json',
                beforeSend: function() {
                    SPCO.do_before_sending_ajax();
                },
                success:    function(response) {
                    CREDIT.process_response(response);
                },
                error:      function(err) {
                    console.log(err);
                    SPCO.ajax_request_server_error();
                },
            });
        },

        /**
         * @function
         * @param  {object} response
         */
        process_response: function(response) {
            if (CREDIT.display_debug) {
                console.log();
                console.log(JSON.stringify('@CREDIT.process_response()', null, 4));
            }

            CREDIT.form_input.val('');
            if (typeof response !== 'undefined' && response !== null) {

                if (CREDIT.display_debug) {
                    SPCO.console_log_object('CREDIT.response', response, 0);
                }

                if (typeof response.errors !== 'undefined') {
                    SPCO.scroll_to_top_and_display_messages(SPCO.main_container, response, true);
					const buttonCredit = document.getElementById("ees-parent-credit-submit");
                    buttonCredit.disabled = true;
                } else if (typeof response.attention !== 'undefined') {
                    SPCO.scroll_to_top_and_display_messages(SPCO.main_container, response, true);
                } else if (typeof response.success !== 'undefined') {
                     SPCO.scroll_to_top_and_display_messages(SPCO.main_container, response, true);
                } else if (typeof response.return_data !== 'undefined') {

                    if (typeof response.return_data.payment_info !== 'undefined') {
                        CREDIT.update_payment_info_table(response);
                        const buttonCredit = document.getElementById("ees-parent-credit-submit");
                        buttonCredit.disabled = true;
     
                        const buttonPromotion = document.getElementById("ee-promotion-code-submit");
                        buttonPromotion.disabled = true;    
                    }

                } else {
                    // oh noes...
                    SPCO.ajax_request_server_error();
                }

            } else {
                SPCO.ajax_request_server_error();
            }
        },
        // end of CREDIT object
    };


    CREDIT.initialize();

});
