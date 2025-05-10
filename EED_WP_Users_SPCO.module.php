<?php

use EventEspresso\core\domain\services\factories\EmailAddressFactory;
use EventEspresso\core\domain\services\validation\email\EmailValidationException;
use EventEspresso\core\domain\values\EmailAddress;
use EventEspresso\core\domain\values\Url;
use EventEspresso\core\exceptions\EntityNotFoundException;
use EventEspresso\core\exceptions\InvalidDataTypeException;
use EventEspresso\core\exceptions\InvalidInterfaceException;
use EventEspresso\core\services\loaders\LoaderFactory;
use EventEspresso\WpUser\domain\services\users\WpUserEmailVerification;

/**
 * EED_WP_Users_SPCO module.  Takes care of WP Users integration with SPCO.
 *
 * @since          1.0.0
 * @package        EE WP Users
 * @subpackage     modules
 * @author         Darren Ethier
 */
class EED_WP_Users_SPCO extends EED_Module
{

    /**
     * All frontend hooks.
     */
    public static function set_hooks()
    {
        // hooks into spco
        /**
         * @todo At some point may provide users the option to toggle whether they want
         * changes made in the registration form to be synced with their user profile.  However,
         * we need to work out:
         *    - If users answer yes, then any existing EE_Attendee record for this user would
         *    have to be updated instead of a new one created (especially in the case of where
         *    any personal system question answers change).  Also the wp_user profile fields
         *    are updated.
         *    - If users answer no, then what happens?  The existing EE_Attendee record (if
         *    any) would have to be left alone, the existing wp user record would be left alone.
         *    However, we would not be able to attached the new attendee record to the user
         *    profile because only ONE should really be attached (otherwise how would autofill
         *    of forms work?).  So perhaps what we'd do when "no" is answered is a new
         *    attendee record is created but just not attached to the user id?  That means there
         *    would be no record of attendee or registration on that user profile (which might be
         *    okay?)
         * In the meantime, for the first iteration, if the user is logged in we assume that the
         * primary registrant data that changes is ALWAYS synced with their user profile (and
         * we'll show a notice to that affect).
         */
        // add_filter(
        //    'FHEE__EE_SPCO_Reg_Step_Attendee_Information__question_group_reg_form__subsections_array',
        //    array( 'EED_WP_Users_SPCO', 'reg_checkbox_for_sync_info' ), 10, 4
        // );
        // add_filter(
        //     'FHEE__EE_SPCO_Reg_Step_Attendee_Information___save_registration_form_input',
        //     array( 'EED_WP_Users_SPCO', 'process_wp_user_inputs' ), 10, 5
        // );
        add_action('wp_enqueue_scripts', array('EED_WP_Users_SPCO', 'enqueue_scripts'));
		
		add_filter(
            'FHEE__EE_SPCO_Reg_Step_Payment_Options___display_payment_options__before_payment_options',
            array('EED_WP_Users_SPCO','add_parent_credit_input') );
        add_filter(
            'FHEE__EEH_Form_Fields__generate_question_groups_html__after_question_group_questions',
            array('EED_WP_Users_SPCO', 'primary_reg_sync_messages'),
            10,
            4
        );
        add_filter(
            'FHEE__EEM_Answer__get_attendee_question_answer_value__answer_value',
            array('EED_WP_Users_SPCO', 'filter_answer_for_wpuser'),
            10,
            4
        );
        // add_filter(
        //     'FHEE_EE_Single_Page_Checkout__save_registration_items__find_existing_attendee',
        //     array('EED_WP_Users_SPCO', 'maybe_sync_existing_attendee'),
        //     10,
        //     3
        // );
        add_filter(
            'FHEE__EE_SPCO_Reg_Step_Attendee_Information___process_registrations__pre_registration_process',
            array('EED_WP_Users_SPCO', 'verify_user_access'),
            10,
            6
        );
        add_action(
            'AHEE__EE_Single_Page_Checkout__process_attendee_information__end',
            array('EED_WP_Users_SPCO', 'process_wpuser_for_attendee'),
            10,
            2
        );
        // notifications
        add_action(
            'AHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_user_created',
            array('EED_WP_Users_SPCO', 'new_user_notifications'),
            10,
            4
        );
        // hook into spco for styles and scripts.
        add_action(
            'AHEE__EED_Single_Page_Checkout__enqueue_styles_and_scripts__attendee_information',
            array('EED_WP_Users_SPCO', 'enqueue_scripts_styles')
        );
        // hook into spco for adding additional reg step
        add_filter(
            'AHEE__SPCO__load_reg_steps__reg_steps_to_load',
            array('EED_WP_Users_SPCO', 'register_login_reg_step')
        );
        // hook into spco reg form for additional information
        add_action(
            'AHEE__attendee_information__reg_step_start',
            array('EED_WP_Users_SPCO', 'maybe_login_notice'),
            10
        );
   
        add_action( 
            'AHEE__thank_you_page_overview_template__bottom',
            array('EED_WP_Users_SPCO','complete_parent_credit_process'), 
            10, 3
        );
		

        EED_WP_Users_SPCO::_add_user_registration_route_hooks();
    }

	public static function ticket_removed_from_cart(EE_Ticket $ticket, int $quantity, EE_Line_Item $parent_item)
	{  
		$user_id = get_current_user_id();
		$cardInfo = self::get_card_info();   
        list($first_item, $grand_total, $cart) = $cardInfo; 
        
		if($first_item)
		{
           $lineCode = 'promotion-3563';
	       $first_parent_item = $first_item->parent();	
		   $parent_credit_item = $first_parent_item->get_child_line_item($lineCode);
		
			if($parent_credit_item)
			{
			  $parent_credit_item->delete_child_line_item($lineCode);
			  EE_WPUsers::delete_parent_credit_history($user_id, $first_item->transaction()->ID());
			}
		}
	}
    
	public static function enqueue_scripts()
    {
        wp_register_style(
            'eea-parent-credit',
            EE_WPUSERS_URL . 'assets/css/eea-parent-credit.css',
            array(),
            EE_WPUSERS_VERSION
        );

        if (apply_filters('EED_Single_Page_Checkout__SPCO_active', false)) {
          wp_register_script(
            'eea-parent-credit-js',
            EE_WPUSERS_URL . 'assets/js/eea-parent-credit.js',
             [ 'jquery','jquery-ui-draggable'],
            EE_WPUSERS_VERSION
         );
	   }
	    wp_enqueue_script('eea-parent-credit-js');
		  
        wp_enqueue_style('eea-parent-credit');
    }

    /**
     * All admin hooks (and ajax)
     */
    public static function set_hooks_admin()
    {
        // hook into filters/actions done on ajax but ONLY EE_FRONT_AJAX requests
        if (defined('EE_FRONT_AJAX') && EE_FRONT_AJAX) {
            add_filter(
                'FHEE__EEH_Form_Fields__generate_question_groups_html__after_question_group_questions',
                array('EED_WP_Users_SPCO', 'primary_reg_sync_messages'),
                10,
                4
            );
            add_filter(
                'FHEE__EEM_Answer__get_attendee_question_answer_value__answer_value',
                array('EED_WP_Users_SPCO', 'filter_answer_for_wpuser'),
                10,
                4
            );
            // add_filter(
            //     'FHEE_EE_Single_Page_Checkout__save_registration_items__find_existing_attendee',
            //     array('EED_WP_Users_SPCO', 'maybe_sync_existing_attendee'),
            //     10,
            //     3
            // );
            add_filter(
                'FHEE__EE_SPCO_Reg_Step_Attendee_Information___process_registrations__pre_registration_process',
                array('EED_WP_Users_SPCO', 'verify_user_access'),
                10,
                6
            );
            add_action(
                'AHEE__EE_Single_Page_Checkout__process_attendee_information__end',
                array('EED_WP_Users_SPCO', 'process_wpuser_for_attendee'),
                10,
                2
            );
            // notifications
            add_action(
                'AHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_user_created',
                array('EED_WP_Users_SPCO', 'new_user_notifications'),
                10,
                4
            );
        }
				
	    add_action(
            'FHEE__EED_Multi_Event_Registration__delete_ticket__ticket_removed_from_cart',
            array('EED_WP_Users_SPCO', 'ticket_removed_from_cart'),
            10,
            3
        );
        // ajax calls
        add_filter(
            'FHEE__EE_SPCO_Reg_Step_Payment_Options___display_payment_options__before_payment_options',
            array('EED_WP_Users_SPCO','add_parent_credit_input') );
        add_action('wp_ajax_submit_parent_credit',  array('EED_WP_Users_SPCO','submit_parent_credit'));
        add_action('wp_ajax_nopriv_submit_parent_credit', array('EED_WP_Users_SPCO','submit_parent_credit'));

        add_action(
            'wp_ajax_ee_process_login_form',
            array('EED_WP_Users_SPCO', 'process_login_form'),
            10
        );
        add_action(
            'wp_ajax_nopriv_ee_process_login_form',
            array('EED_WP_Users_SPCO', 'process_login_form'),
            10
        );
        // send admin notification about user having trouble.
        add_action(
            'wp_ajax_ee_process_user_trouble_notification',
            array('EED_WP_Users_SPCO', 'send_notification_to_admin')
        );
        add_action(
            'wp_ajax_nopriv_ee_process_user_trouble_notification',
            array('EED_WP_Users_SPCO', 'send_notification_to_admin')
        );
	

        EED_WP_Users_SPCO::_add_user_registration_route_hooks();
    }


      /**
     *    _get_payment_info
     *
     * @param EE_Cart $cart
     * @return    array
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function _get_payment_info(EE_Cart $cart): array
    {
        EEH_Autoloader::register_line_item_filter_autoloaders();
        $line_item_filter_processor = new EE_Line_Item_Filter_Processor(
            apply_filters(
                'FHEE__SPCO__EE_Line_Item_Filter_Collection',
                new EE_Line_Item_Filter_Collection()
            ),
            $cart->get_grand_total()
        );
		
        /** @var EE_Line_Item $filtered_line_item_tree */
        $filtered_line_item_tree = $line_item_filter_processor->process();
        // autoload Line_Item_Display classes
        EEH_Autoloader::register_line_item_display_autoloaders();
		
        // $this->checkout->line_item_filters();
        $Line_Item_Display = new EE_Line_Item_Display('spco');
        return [
            'payment_info' => $Line_Item_Display->display_line_item(
                $filtered_line_item_tree,
                ['display_event_row' => true]
            ),
            'cart_total'   => $filtered_line_item_tree->total(),
        ];
    }

	  function console_log($output, $with_script_tags = true) {
	$js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) .
	');';
	if ($with_script_tags) {
	$js_code = '<script>' . $js_code . '</script>';
	}
	echo $js_code;
	}

    private static function get_card_info()
    {
        $checkout = EE_Registry::instance()->SSN->checkout();
        $transaction = $checkout->transaction;
        $current_step = $checkout->current_step;
        $cart = $current_step->checkout->cart;
		$grand_total = $cart->get_grand_total();
        $purchases = $grand_total->get_items();
        $first_item = reset($purchases);

        return  [$first_item, $grand_total, $cart];
    }

    private static function add_credit_records($event_name, $credit_amount, $user_id, $event_id, $reg_id, $price, $transaction_id )
    {

        $valid_debit_recs = EE_WPUsers::get_unexpired_debit_records($user_id);
        $total_credit_amount = $credit_amount;
        foreach ($valid_debit_recs as $index => $valid_debit_rec)
        {
           $used_amount = min($credit_amount, $valid_debit_rec['credit_amount']);
           EE_WPUsers::add_credit_history(false, $event_name , $used_amount, $user_id, 0, 
           $event_id,  $reg_id , 'C', $price,  $transaction_id, null, $valid_debit_rec['id'] , null, $total_credit_amount);		
           $credit_amount = $credit_amount - $used_amount;
           if ($credit_amount == 0)
           {
             break;
           }
        } 
    }

    public static function submit_parent_credit()
    {
        try{
	   $user_id = get_current_user_id();
       $parent_credit_balance = EE_WPUsers::get_credit_total_balance($user_id);
       $JSON_response = [];
       $return_data =[];
       
		if($parent_credit_balance == null || $parent_credit_balance == 0)
		{
          $JSON_response['errors'] = sprintf( esc_html__(
                'You do not have any credit!',
                'event_espresso'
            ));
           $JSON_response = apply_filters('FHEE__EED_Promotions__generate_JSON_response__JSON_response', $JSON_response);
         // return encoded array
           wp_send_json($JSON_response);
		}
        
        $cardInfo = self::get_card_info();   
        list($first_item, $grand_total, $cart) = $cardInfo;  	
        $first_parent_item = $first_item->parent();		  
		$price = $first_parent_item->unit_price();
			 
		if( $price == 0)
		{
			$JSON_response['errors'] = sprintf( esc_html__(
                'Your credit is not applicaple on this transaction!',
                'event_espresso'
                ));

			wp_send_json($JSON_response);
			return;
		}
			 
		$credit_amount = min($price, $parent_credit_balance);
		$event_id=  $first_parent_item->OBJ_ID();
		$event_name = $first_item->ticket()->get_event_name();
			 
		$sub_line_item = EE_Line_Item::new_instance(array(
                'LIN_name'       => 'Parent Credit',
                'LIN_desc'       => '$'.$credit_amount.' credit applied',
                'LIN_quantity'   => 1,
                'LIN_is_taxable' => false,
                'LIN_total'      => -1 * $credit_amount,
                'LIN_type'       => EEM_Line_Item::type_line_item,
                'OBJ_type'       => EEM_Line_Item::OBJ_TYPE_PROMOTION,
				'OBJ_ID'         => 3563 // Parent Credit Promotion ID	
            ));
			 
        $sub_line_item = apply_filters(
            'FHEE__EEH_Line_Item__create_ticket_line_item__sub_line_item',
             $sub_line_item
        );
 
        $sub_line_item->set_unit_price(-1 * $credit_amount);
        $first_parent_item->add_child_line_item($sub_line_item);
			 
        $sub_line_item->save();

		foreach ($first_item->transaction()->registrations() as $registration ) {
			if ( $registration instanceof EE_Registration ) {
				$reg_id = $registration->ID();
				break;
			}
		}
     
        self::add_credit_records($event_name, $credit_amount, $user_id, $event_id, $reg_id, $price, $first_item->transaction()->ID());

		$grand_total->recalculate_total_including_taxes();

        $cart->save_cart(false);

        $return_data            = self::_get_payment_info($cart);
        $return_data['success'] = 'Parent credit applied successfully!';
     
        EED_Single_Page_Checkout::update_checkout();

        self::generate_JSON_response($return_data);
      } 
      catch (Exception $e) {
            $JSON_response['errors'] = sprintf( esc_html__(
            $e->getMessage()
            ));

          wp_send_json($JSON_response);
           return;
        }
    }

    public function generate_JSON_response(array $return_data = [])
    {
        $JSON_response = [];
        // grab notices
        $notices = EE_Error::get_notices(false);
        // add notices to JSON response, but only if they exist
        if (isset($notices['attention'])) {
            $JSON_response['attention'] = $notices['attention'];
        }
        if (isset($notices['errors'])) {
            $JSON_response['errors'] = $notices['errors'];
        }
        if (isset($notices['success'])) {
            $JSON_response['success'] = $notices['success'];
        }
        if (empty($JSON_response) && empty($return_data)) {
            $JSON_response['errors'] = sprintf(
                esc_html__(
                    'Sorry, but the parent credite could not be applied because the credit could not be retrieved.',
                    'event_espresso'
                ),
                strtolower($this->config()->label->singular),
                '<br />'
            );
        }
        // add return_data array to main JSON response array, IF it contains anything
        $JSON_response['return_data'] = $return_data;
        // filter final array
        $JSON_response = apply_filters('FHEE__EED_Promotions__generate_JSON_response__JSON_response', $JSON_response);
        // return encoded array
        wp_send_json($JSON_response);
    }

    public static function complete_parent_credit_process($transaction)
    {
        $user_id = get_current_user_id();

        EE_WPUsers::update_parent_credit_history($user_id, $transaction->ID());
    }
    /**
     * Adds hook points that are used for handling actions on the wp user registration process.
     */
    protected static function _add_user_registration_route_hooks()
    {
        // do auto login after registration of new user
        if (! has_action(
            'register_new_user',
            array('EED_WP_Users_SPCO', 'auto_login_registered_user')
        )
        ) {
            add_action('register_new_user', array('EED_WP_Users_SPCO', 'auto_login_registered_user'));
            add_action('register_form', array('EED_WP_Users_SPCO', 'add_auto_login_parameter'));
        }
    }

    /**
     * Callback for AHEE__EED_Single_Page_Checkout__enqueue_styles_and_scripts__attendee_information
     * used to register and enqueue scripts for wp user integration with spco.
     *
     * @since 1.0.0
     * @param EED_Single_Page_Checkout $spco
     * @return void
     */
    public static function enqueue_scripts_styles(EED_Single_Page_Checkout $spco)
    {
        wp_register_script(
            'ee-dialog',
            EE_PLUGIN_DIR_URL . 'core/admin/assets/ee-dialog-helper.js',
            array('jquery', 'jquery-ui-draggable'),
            EVENT_ESPRESSO_VERSION,
            true
        );
        wp_register_script(
            'eea-wp-users-integration-spco',
            EE_WPUSERS_URL . 'assets/js/eea-wp-users-integration-spco.js',
            array('single_page_checkout', 'ee-dialog'),
            EE_WPUSERS_VERSION,
            true
        );
        wp_register_style(
            'eea-wp-users-integration-spco-style',
            EE_WPUSERS_URL . 'assets/css/eea-wp-users-integration-spco.css',
            array(),
            EE_WPUSERS_VERSION
        );

        wp_enqueue_script('eea-wp-users-integration-spco');
        wp_enqueue_style('eea-wp-users-integration-spco-style');
        // add hidden login form in footer if user is not logged in that will get called if user needs to log in.
        if (! is_user_logged_in()) {
            add_action('wp_footer', array('EED_WP_Users_SPCO', 'login_form_skeleton'), 100);
        }
    }


    /**
     * Needs to be defined because it is abstract
     *
     * @since 1.0.0
     * @param WP $WP
     * @return void
     */
    public function run($WP)
    {
    }


    /**
     * @return WpUserEmailVerification
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidInterfaceException
     * @throws InvalidDataTypeException
     */
    protected static function getWpUserEmailVerification()
    {
        return LoaderFactory::getLoader()->getShared(
            'EventEspresso\WpUser\domain\services\users\WpUserEmailVerification'
        );
    }


    /**
     * callback for FHEE__EEH_Form_Fields__generate_question_groups_html__after_question_group_questions.
     * Used to add a message in certain conditions for the logged in user about syncing of answers
     * given in the reg form with their user profile.
     *
     * @param string                                $content Any content already added here.
     * @param EE_Registration                       $registration
     * @param EE_Question_Group                     $question_group
     * @param EE_SPCO_Reg_Step_Attendee_Information $spco
     * @return string                                content to return
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function primary_reg_sync_messages(
        $content,
        EE_Registration $registration,
        EE_Question_Group $question_group,
        EE_SPCO_Reg_Step_Attendee_Information $spco
    ) {
        if ((
                ! is_user_logged_in()
                || (
                    is_user_logged_in()
                    && ! $registration->is_primary_registrant()
                )
                || $question_group->ID() !== EEM_Question_Group::system_personal
            )
            || ! EE_Registry::instance()->CFG->addons->user_integration->sync_user_with_contact
        ) {
            return $content;
        }
        return  $content;
    }


    /**
     * callback for FHEE__EE_SPCO_Reg_Step_Attendee_Information__question_group_reg_form__subsections_array
     * with the purpose of outputting confirmation checkbox for users to indicate they wish changes in
     * the reg form to be reflected on the profile attached to their account.  Note this ONLY should
     * appear if the user is logged in.
     *
     * @todo  THIS IS NOT IMPLEMENTED YET.
     * @param array                                 $form_subsections existing form subsections
     * @param EE_Registration                       $registration
     * @param EE_Question_Group                     $question_group
     * @param EE_SPCO_Reg_Step_Attendee_Information $spco
     * @return array content
     * @throws EE_Error
     */
    public static function reg_checkbox_for_sync_info(
        $form_subsections,
        EE_Registration $registration,
        EE_Question_Group $question_group,
        EE_SPCO_Reg_Step_Attendee_Information $spco
    ) {
        if (! is_user_logged_in() || ! $registration->is_primary_registrant()) {
            return $form_subsections;
        }
        $identifier             = 'sync_with_user_profile';
        $input_constructor_args = array(
            'html_name'        => 'ee_reg_qstn[' . $registration->ID() . '][' . $identifier . ']',
            'html_id'          => 'ee_reg_qstn-' . $registration->ID() . '-' . $identifier,
            'html_class'       => 'ee-reg-qstn',
            'required'         => true,
            'html_label_id'    => 'ee_reg_qstn-' . $registration->ID() . '-' . $identifier,
            'html_label_class' => 'ee-reg-qstn',
            'html_label_text'  => esc_html__('Sync changes with your user profile?', 'event_espresso'),
            'default'          => true,
        );
        $form_subsections[ $identifier ] = new EE_Yes_No_Input($input_constructor_args);
        return $form_subsections;
    }


    /**
     * callback for FHEE__EE_SPCO_Reg_Step_Attendee_Information___save_registration_form_input
     * that we'll read to remove and process any form input injected by WP_User_Integration into the
     * registration process.
     *
     * @todo  THIS IS NOT IMPLEMENTED YET.
     * @param bool                                  $processed      return true to stop normal spco processing of input
     * @param EE_Registration                       $registration
     * @param string                                $form_input     The input.
     * @param mixed                                 $input_value    The normalized input value.
     * @param EE_SPCO_Reg_Step_Attendee_Information $spco
     * @return bool                                                 return true to stop normal spco processing
     *                                                              or false to keep it going.
     */
    public static function process_wp_user_inputs(
        $processed,
        EE_Registration $registration,
        $form_input,
        $input_value,
        EE_SPCO_Reg_Step_Attendee_Information $spco
    ) {
        return $form_input === 'sync_with_user_profile';
    }

    /**
     * Added to filter that processes the return to the registration form of whether and answer to the question exists
     * for that
     *
     * @param mixed            $value
     * @param EE_Registration $registration
     * @param int|string      $question_id in 4.8.10 and 4.8.12 it is numeric (eg 23) but in 4.8.11 it is a system ID
     *                                     like "email"
     * @param string          $system_id   passed in 4.8.12+ of EE core
     * @return mixed
     */
    public static function filter_answer_for_wpuser(
        $value,
        EE_Registration $registration,
        $question_id,
        $system_id = null
    ) {
        $firstname = 0;
        $lastname  = 0;
        $email     = 0;
        $address   = 0;
        $address2  = 0;
        $phone     = 0;
        $city      = 0;
        $country   = 0;
        $state     = 0;
        $zip       = 0;
        $id_to_use = -1;
        // only fill for primary registrant
        if (! $registration->is_primary_registrant()) {
            return $value;
        }
        if (empty($value)) {
            $current_user = wp_get_current_user();
                   /**
             * there was a temporary bug in EE core relating to $question_id being passed
             * in 4.8.10 it was a question's ID (eg 23)
             * but in 4.8.11 it was changed to a SYSTEM ID (eg 'email')
             * (and the new constants, like EEM_Attendee::system_question_fname, were introduced)
             * but soon thereafter in order to fix that bug it was changed
             * BACK to a proper question ID (eg 23) and a new parameter was passed,
             * $system_id
             */

        
                // 4.8.11-style. Use the new constants
                $address     = 4;
                $address2    = 5;
                $city       = 6;
                $country    = 7;
                $state      = 8;
                $zip        = 9;
                $firstname  = 13;
                $lastname   = 14;
                $email      = 15;
                $phone      = 11;    
                $id_to_use = $question_id; 
            }

           if ($current_user instanceof WP_User) {
                switch ($id_to_use) {
                    case $firstname:
                        $value = $current_user->get('first_name');
                        break;
                    case $lastname:
                        $value = $current_user->get('last_name');
                        break;
                    case $email:
                        $value = $current_user->get('user_email');
                        break;
                    case $address:
                        $value =  get_user_meta( get_current_user_id(), 'paddress', true );
                        break;
                    case $address2:
                        $value =  get_user_meta( get_current_user_id(), 'psecondaddress', true );
                        break;
                    case $phone:
                        $value =  get_user_meta( get_current_user_id(), 'phone', true );
                        break;
                    case $country:
                        $value =  get_user_meta( get_current_user_id(), 'country', true );
                        break;
                    case $city:
                        $value =  get_user_meta( get_current_user_id(), 'city', true );
                        break;
                    case $state:
                        $value =  get_user_meta( get_current_user_id(), 'state', true );
                        break;
                    case $zip:
                        $value =  get_user_meta( get_current_user_id(), 'zip', true );
                        break;
                    default:
                }
           }
        return $value;
    }


    /**
     * callback for FHEE__EE_SPCO_Reg_Step_Attendee_Information___process_registrations__pre_registration_process.
     * In this callback we check if the submitted email address:
     *    - matches the email address of a user in the system.
     *    - If it does, then we have logic to determine whether we fail or pass the registration
     *    depending on user privileges.
     *
     * @param bool                                  $stop_processing        This is what the current process is set at.
     *                                                                      If true, then we should just return because
     *                                                                      it means another plugin already failed the
     *                                                                      processing.
     * @param                                       $att_nmbr
     * @param EE_Registration                       $spco_registration
     * @param EE_Registration[]                     $registrations
     * @param array                                 $valid_data             incoming post data.
     * @param EE_SPCO_Reg_Step_Attendee_Information $spco
     * @return bool                                false to NOT stop the process, true to stop the process.
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     * @throws EmailValidationException
     * @throws DomainException
     */
    public static function verify_user_access(
        $stop_processing,
        $att_nmbr,
        EE_Registration $spco_registration,
        $registrations,
        $valid_data,
        EE_SPCO_Reg_Step_Attendee_Information $spco
    ) {
        if ($att_nmbr !== 0 || $stop_processing) {
            // get out because we've already either verified things or another plugin is halting things.
            return $stop_processing;
        }
        $user_notice     = '';
        $field_input_error = array();
        // we need to loop through each valid_data[$registration->reg_url_link()] set of data
        // to see if there is a user existing for that email address.
        // If there is then halt the presses!
        foreach ($registrations as $registration) {
            // if not a valid $reg then we'll just ignore and let spco handle it
            if (! $registration instanceof EE_Registration) {
                return $stop_processing;
            }
            $reg_url_link = $registration->reg_url_link();
            if (isset($valid_data[ $reg_url_link ]) && is_array($valid_data[ $reg_url_link ])) {
                foreach ($valid_data[ $reg_url_link ] as $form_section => $form_inputs) {
                    if (! is_array($form_inputs)) {
                        continue;
                    }
                    /**
                     * Allow plugin authors to skip this check.  If plugin authors want to return their own error
                     * responses, then they will need to also filter the stop_processing param at the end of this
                     * method to return true;
                     */
                    if (! empty($form_inputs['15'])
                        && apply_filters(
                            'EED_WP_Users_SPCO__verify_user_access__perform_email_user_match_check',
                            true,
                            $spco,
                            $registration
                        )
                    ) {
                        $user_notice = EED_WP_Users_SPCO::getUserLoginNotice(
                            EmailAddressFactory::create($form_inputs['15'])
                        );
                        if ($user_notice !== '') {
                            $user_notice .= EED_WP_Users_SPCO::loginAndRegisterButtonsHtml(
                                new Url($spco->reg_step_url())
                            );
                            $stop_processing     = true;
                            $field_input_error[] = 'ee_reg_qstn-' . $registration->ID() . '-15';
                            break 2;
                        }
                    }
                }
            }
        }
        if ($stop_processing) {
            EE_Error::add_error($user_notice, __FILE__, __FUNCTION__, __LINE__);
            $spco->checkout->json_response->set_return_data(
                array(
                    'wp_user_response' => array(
                        'require_login'          => true,
                        'show_login_form'        => false,
                        'show_errors_in_context' => true,
                        'validation_error'       => array('field' => $field_input_error),
                    ),
                )
            );
            return $stop_processing;
        }
        return apply_filters('EED_WP_Users_SPCO__verify_user_access__stop_processing', $stop_processing, $spco);
    }


    /**
     * @param EmailAddress $wp_user_email_address
     * @return string
     * @throws EmailValidationException
     * @throws InvalidInterfaceException
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws EE_Error
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    private static function getUserLoginNotice(EmailAddress $wp_user_email_address)
    {
        $login_required_message  = '<p>'
                                   . esc_html__(
                                       'You have entered an email address that matches an existing user account in our system.  If this is your email address, please log in before continuing your registration. Otherwise, register with a different email address.',
                                       'event_espresso'
                                   )
                                   . '</p>';
        $user_mismatch_message = '<p>'
                                 . esc_html__(
                                     'You have entered an email address that matches an existing user account in our system.  You can only submit registrations for your own account or for a person that does not exist in the system.  Please use a different email address.',
                                     'event_espresso'
                                 )
                                 . '</p>';
        $wp_user_email_verification = EED_WP_Users_SPCO::getWpUserEmailVerification();
        return $wp_user_email_verification->getWpUserEmailVerificationNotice(
            $wp_user_email_verification->verifyWpUserEmailAddress($wp_user_email_address),
            $login_required_message,
            $user_mismatch_message,
            '',
            ''
        );
    }


    /**
     * @param Url $redirect_to_url_after_login
     * @return string
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function loginAndRegisterButtonsHtml(Url $redirect_to_url_after_login)
    {
        /**
         * @todo ideally the redirect url would come
         * back to the same page after login.  For
         * now we're just utilizing js/ajax for login
         * processing so users with js supported
         * browsers will just stay on the loaded page.
         */
        $buttons = '<a class="float-right ee-wpuser-login-button" href="'
                   . wp_login_url($redirect_to_url_after_login->getFullUrl())
                   . '">'
                   . '<button type="button" class="button button-primary">'
                   . esc_html__('Login', 'event_espresso') . '</button>'
                   . '</a>';
        if (get_option('users_can_register')) {
            $registration_url = ! EE_Registry::instance()->CFG->addons->user_integration->registration_page
                ? add_query_arg(
                    array(
                        'ee_do_auto_login' => 1,
                        'ee_load_on_login' => 1,
                        'redirect_to'      => $redirect_to_url_after_login->getFullUrl(),
                    ),
                    wp_registration_url()
                )
                : EE_Registry::instance()->CFG->addons->user_integration->registration_page;
            $buttons           .= '<a class="ee-wpuser-register-link float-right" href="'
                                 . $registration_url
                                 . '">'
                                 . '<button type="button" class="button button-primary">'
                                 . esc_html__('Register', 'event_espresso')
                                 . '</button>'
                                 . '</a>';
        }
        $buttons .= '<div style="clear:both"></div>';
        return $buttons;
    }

    /**
     * Callback for AHEE__SPCO__before_registration_steps action hook to display a login required notice if revisiting
     * to edit attendee information.
     *
     * @param EE_SPCO_Reg_Step_Attendee_Information $reg_step
     * @return void  echoes HTML content to show before the reg form.
     * @throws EE_Error
     */
    public static function maybe_login_notice(EE_SPCO_Reg_Step_Attendee_Information $reg_step)
    {
        // first if this isn't a revisit OR $reg_step is invalid then get out nothing to see here.
        if (! $reg_step->checkout->revisit) {
            return;
        }
        $content = '';
        // keeping the message simple for now.
        // If user is not logged in, and event for the displayed registration automatically
        // creates registrations, then they must log in before editing registration.
        $registrations      = $reg_step->checkout->transaction->registrations($reg_step->checkout->reg_cache_where_params);
        $event_creates_user = false;
        if ($registrations) {
            foreach ($registrations as $registration) {
                if ($reg_step->checkout->visit_allows_processing_of_this_registration($registration)
                    &&  EE_WPUsers::is_auto_user_create_on($registration->event_ID())
                ) {
                    $event_creates_user = true;
                    // once we've determined this we don't need to keep looping
                    break;
                }
            }
        }
        if ($event_creates_user && ! is_user_logged_in()) {
            $content       = '<div class="ee-attention">';
            $inner_content = '<p>'
                             . sprintf(
                                 esc_html__(
                                     'You are only able to edit your information once you have %slogged in%s.  If you recently registered, please check your email for your account information which will allow you to log in.',
                                     'event_espresso'
                                 ),
                                 '<a href="' . wp_login_url() . '">',
                                 '</a>'
                             )
                             . '</p>';
            // provide link to notify the admin about unreceived emails.
            $inner_content          .= '<p><span class="ee-send-email-info-text">'
                                       . sprintf(
                                           esc_html__(
                                               'If you did not receive any emails, please %sclick here%s to notify us and we will followup with you to get you setup.',
                                               'event_espresso'
                                           ),
                                           '<a href="#" class="js-toggle-followup-notification">',
                                           '</a>'
                                       )
                                       . '</span></p>';
            $inner_content          = apply_filters(
                'FHEE__EED_WP_Users_SPCO__maybe_login_notice__inner_content',
                $inner_content,
                $reg_step
            );
            $email_input_and_button = '<div class="ee-attention-notification-form hidden">';
            $email_input_and_button .= '<label for="notification-email-contact">'
                                       . esc_html__('Email to contact you with:', 'event_espresso')
                                       . '</label><input type="text" id="notification-email-contact">'
                                       . '<a class="ee-roundish ee-orange ee-button js-submit-notification-followup">'
                                       . esc_html__('Notify Us!', 'event_espresso')
                                       . '</a>';
            $email_input_and_button .= '</div>';
            $content                .= $inner_content . $email_input_and_button . '</div>';
        }
        echo $content;
    }


    /**
     * This is the callback for FHEE_EE_Single_Page_Checkout__save_registration_items__find_existing_attendee
     * In this callback if the user is logged in and the registration being processed is the primary
     * registration, then we will make sure we're always updating the existing attendee record
     * attached to the wp_user regardless of what might have been detected by spco.
     * However, behaviour is controlled by EE_Config->addons->user_integration->sync_user_with_contact and no syncing
     * will happen if this is set to false and there is no existing relationship between a contact and a wpuser.
     *
     * @param null|EE_Attendee $existing_attendee                   Possibly an existing attendee
     *                                                              already detected by SPCO
     * @param EE_Registration  $registration
     * @param array            $attendee_data                       array of core personal data used to verify if
     *                                                              existing attendee exists.
     * @return EE_Attendee|null
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function maybe_sync_existing_attendee(
        $existing_attendee,
        EE_Registration $registration,
        $attendee_data) {
        if (! is_user_logged_in() || (is_user_logged_in() && ! $registration->is_primary_registrant())) {
            return $existing_attendee;
        }
        $user = get_userdata(get_current_user_id());
        if (! $user instanceof WP_User) {
            return $existing_attendee;
        }
        // existing attendee on user?
        $att = self::get_attendee_for_user($user);
        /**
         * if there already IS an existing attendee then that means the system found one matching
         * the first_name, last_name, and email address that is incoming.  If this attendee is NOT
         * what is attached to the user, then we'll change the first name and last name but not the
         * email address.  Otherwise we could end up with two WP users in the system with the
         * same email address.
         * Here we also skip the user sync if the EE_WPUsers_Config->sync_user_with_contact option is false
         */
        if (! $att instanceof EE_Attendee
            || ! EE_Registry::instance()->CFG->addons->user_integration->sync_user_with_contact) {
            return $existing_attendee;
        }
        if ($existing_attendee instanceof EE_Attendee && $att->ID() !== $existing_attendee->ID()) {
            // only change first and last name for att, we'll leave the email address alone regardless of what its at.
            if (! empty($attendee_data['ATT_fname'])) {
                $att->set_fname($attendee_data['ATT_fname']);
            }
            if (! empty($attendee_data['ATT_lname'])) {
                $att->set_lname($attendee_data['ATT_lname']);
            }
        } else {
            // change all
            if (! empty($attendee_data['ATT_fname'])) {
                $att->set_fname($attendee_data['ATT_fname']);
            }
            if (! empty($attendee_data['ATT_lname'])) {
                $att->set_lname($attendee_data['ATT_lname']);
            }
            if (! empty($attendee_data['ATT_email'])) {
                $att->set_email($attendee_data['ATT_email']);
            }
        }
        return $att;
    }


    /**
     * callback for AHEE__EE_Single_Page_Checkout__process_attendee_information__end
     * Here's what happens in this callback:
     *    - currently only action happens on the primary registrant.
     *    - If user is logged in then updates etc were already taken care of for EE_Attendee via
     *      self::maybe_sync_existing_attendee (cause we returned the attached attendee for the
     *      user to the attendee processor).  However, we will sync the given details with the WP
     *      User Profile.
     *     - If user is NOT logged in, then we create a user for the primary registrant data but ONLY
     *       if there is not already a user existing for the given attendee data AND only if automatic
     *       user creation is turned on for this event.
     *
     * @param EE_SPCO_Reg_Step_Attendee_Information $spco
     * @param array                                 $valid_data The incoming form post data
     *                                                          (that has already been validated)
     * @return void
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     * @throws EntityNotFoundException
     */
    public static function process_wpuser_for_attendee(EE_SPCO_Reg_Step_Attendee_Information $spco, $valid_data)
    {
        $user_created = false;
        $att_id       = '';
        $parentEmail = '';
        $parentFirstName = '';
        $parentLastName = '';
        $parentAddress = '';
        $parentAddress2 = '';
        $phone = '';
        $city = '';
        $state = '';
        $zip = '';
        $country = '';
        // use spco to get registrations from the
        $registrations = self::_get_registrations($spco);
        foreach ($registrations as $registration) {
            // is this the primary registrant?  If not, continue
            // if (! $registration->is_primary_registrant()) {
            //     continue;
            // }
            //ALPHASTAR

            foreach ($registration->answers() as $key => $value) {
                $key;
                if ($value->question()->admin_label() == "Parent's Email Address") {
                    $parentEmail = $value->value();
                }  
                if ($value->question()->admin_label() == "Parent's First Name") {
                    $parentFirstName = $value->value();
                }
                if ($value->question()->admin_label() == "Parent's Last Name") {
                    $parentLastName = $value->value();
                }      
                
                if ($value->question()->admin_label() == "Parent's Phone Number") {
                    $phone = $value->value();
                }
            }

            $attendee = $registration->attendee();
           
            if($attendee->address() && $attendee->address() != "")
            {
                $parentAddress = $attendee->address();
                $parentAddress2 = $attendee->address2();
                $country = $attendee->country();
                $state = $attendee->state_ID();
                $city = $attendee->city();
                $zip = $attendee->zip();                      
            }

            if (! $attendee instanceof EE_Attendee) {
                // should always be an attendee, but if not we continue just to prevent errors.
                continue;
            }
            // if user logged in, then let's just use that user.  Otherwise we'll attempt to get a
            // user via the attendee info.
            if (is_user_logged_in()) {
                $user = get_userdata(get_current_user_id());
            } else {
                // is there already a user for the given attendee?
                $user = get_user_by('email', $parentEmail);
                // does this user have the same att_id as the given att?
                // If NOT, then we do NOT update because it's possible there was a family member
                // or something sharing the same email address but is a different attendee record.
                //ALPHASTAR
                // $att_id = $user instanceof WP_User ? get_user_option('EE_Attendee_ID', $user->ID) : $att_id;
                // if (! empty($att_id) && $att_id !== $attendee->ID()) {
                //     return;
                // }
            }
            $event = $registration->event();
            // no existing user? then we'll create the user from the date in the attendee form.
            if (! $user instanceof WP_User) {
                // if this event does NOT allow automatic user creation then let's bail.
                if (! EE_WPUsers::is_auto_user_create_on($event)) {
                    return; // no we do NOT auto create users please.
                }
                $password = wp_generate_password(12, false);
                // remove our action for creating contacts on creating user because we don't want to loop!
                remove_action('user_register', array('EED_WP_Users_Admin', 'sync_with_contact'));
                $user_id      = wp_create_user(
                    $parentEmail,
                    $password,
                    $parentEmail
                );
                $user_created = true;
                if ($user_id instanceof WP_Error) {
                    return; // get out because something went wrong with creating the user.
                }
                $user = new WP_User($user_id);
                update_user_option(
                    $user->ID,
                    'description',
                    apply_filters(
                        'FHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_description_field',
                        esc_html__('Registered via event registration form', 'event_espresso'),
                        $user,
                        $attendee,
                        $registration
                    )
                );                
            }
            // only do the below if syncing is enabled.
            if ($user_created || EE_Registry::instance()->CFG->addons->user_integration->sync_user_with_contact) {
                // remove our existing action for updating users via saves in the admin to prevent recursion
                remove_action('profile_update', array('EED_WP_Users_Admin', 'sync_with_contact'));
                wp_update_user(
                    array(
                        'ID'           => $user->ID,
                        'nickname'     => $parentFirstName,
                        'display_name' => $parentFirstName. " " . $parentLastName,
                        'first_name'   => $parentFirstName,
                        'last_name'    => $parentLastName
                    )
                );

                update_user_meta( $user->ID, 'paddress', $parentAddress );
                update_user_meta( $user->ID, 'psecondaddress', $parentAddress2 );
                update_user_meta( $user->ID, 'phone', $phone );
                update_user_meta( $user->ID, 'city', $city );
                update_user_meta( $user->ID, 'country', $country );
                update_user_meta( $user->ID, 'zip', $zip );
                update_user_meta( $user->ID, 'state', $state );
            }
            // if user created then send notification and attach attendee to user
            if ($user_created) {
                do_action(
                    'AHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_user_created',
                    $user,
                    $attendee,
                    $registration,
                    $password
                );
                // set user role
                $user->set_role(EE_WPUsers::default_user_create_role($event));
                update_user_option($user->ID, 'EE_Attendee_ID-'.$attendee->ID(), $attendee->ID());
            } else {
                do_action(
                    'AHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_user_updated',
                    $user,
                    $attendee,
                    $registration
                );
            }
            // failsafe just in case this is a logged in user
            // not created by this system that has never had an attendee record.
            $att_id = empty($att_id) ? get_user_option('EE_Attendee_ID-'.$attendee->ID(), $user->ID) : $att_id;
            if (empty($att_id) && EED_WP_Users_SPCO::_can_attach_user_to_attendee($attendee, $user)) {
                update_user_option($user->ID, 'EE_Attendee_ID-'.$attendee->ID(), $attendee->ID());
            }
        } //end registrations loop
    }


    /**
     * This is used to verify whether its okay to attach an attendee to a user.
     * It compares the first name, last name and email address of the attendee with the first name, last name, and email
     * address of the given WP_User profile.  If there is a mismatch, then no attachment can happen.  If there is a
     * match, then we will attach. A pre check is done for
     * EE_Registry::instance()->CFG->addons->user_integration->sync_user_with_contact and if that's true, then we
     * return true.
     *
     * @param EE_Attendee $attendee
     * @param WP_User     $user
     * @return bool       True means the user can be attached to the attendee, false means it cannot be attached.
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    protected static function _can_attach_user_to_attendee(EE_Attendee $attendee, WP_User $user)
    {
        return
            EE_Registry::instance()->CFG->addons->user_integration->sync_user_with_contact
            || (
                $attendee->fname() === $user->first_name
                && $attendee->lname() === $user->last_name
                && $attendee->email() === $user->user_email
            );
    }


    /**
     * This is the callback for AHEE__EED_WP_Users_SPCO__process_wpuser_for_attendee__user_user_created action.
     * Used to send off notifications when new user created.
     *
     * @since  1.0.0
     * @param WP_User         $user     user that was created
     * @param EE_Attendee     $attendee
     * @param EE_Registration $registration
     * @param string          $password password used to create user.
     * @return void
     */
    public static function new_user_notifications(
        WP_User $user,
        EE_Attendee $attendee,
        EE_Registration $registration,
        $password
    ) {
        // for now we just use the existing core wp notifications.
        global $wp_version;
        if (version_compare($wp_version, '4.3.1', '<')) {
            wp_new_user_notification($user->ID, $password);
        } else {
            wp_new_user_notification($user->ID, null, 'both');
        }
    }


    /**
     * This grabs all the registrations from the given object.
     *
     * @param EE_SPCO_Reg_Step_Attendee_Information $spco
     * @return EE_Registration[]
     * @throws EE_Error
     */
    public static function _get_registrations(EE_SPCO_Reg_Step_Attendee_Information $spco)
    {
        $registrations = array();
        if ($spco->checkout instanceof EE_Checkout && $spco->checkout->transaction instanceof EE_Transaction) {
            $registrations = $spco->checkout->transaction->registrations(
                $spco->checkout->reg_cache_where_params,
                true
            );
        }
        return $registrations;
    }


    /**
     * Returns the EE_Attendee object attached to the given wp user.
     *
     * @param mixed WP_User | int $user_or_id can be WP_User or the user_id.
     * @return EE_Attendee|null
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function get_attendees_for_user($user_or_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . "usermeta";
        $user_id  = $user_or_id instanceof WP_User ? $user_or_id->ID : (int) $user_or_id;
        $query = "SELECT meta_value FROM `$table` WHERE user_id = ".$user_id. " AND meta_key LIKE '%EE_Attendee_ID%'";

        $attIDS = $wpdb->get_results($query, 'ARRAY_A');
        $attendees = null;

        foreach ($attIDS as $index => $attID) {
            $attendee = null;
            if ($attID) {
                $attendee = EEM_Attendee::instance()->get_one_by_ID($attID['meta_value']);
                $attendees[] = $attendee instanceof EE_Attendee ? $attendee : null;
            }
        }
        return $attendees;
    }

    /**
     * Returns the EE_Attendee object attached to the given wp user.
     *
     * @param mixed WP_User | int $user_or_id can be WP_User or the user_id.
     * @return EE_Attendee|null
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function get_attendee_for_user($user_or_id)
    {
        $user_id  = $user_or_id instanceof WP_User ? $user_or_id->ID : (int) $user_or_id;
        $attID    = get_user_option('EE_Attendee_ID', $user_id);
        $attendee = null;
        if ($attID) {
            $attendee = EEM_Attendee::instance()->get_one_by_ID($attID);
            $attendee = $attendee instanceof EE_Attendee ? $attendee : null;
        }
        return $attendee;
    }

    /**
     * Callback for wp_footer.
     * This is only called when user is not logged in on SPCO page loads.  This simply prints the
     * skeleton of a login form for usage when user needs to login.
     *
     * @since 1.0.0
     * @return void
     * @throws DomainException
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     * @throws ReflectionException
     */
    public static function login_form_skeleton()
    {
        // dialog container for dialog helper
        $d_cont = '<div class="ee-admin-dialog-container auto-hide" style="display:none;">' . "\n";
        $d_cont .= '<div class="ee-notices"></div>';
        $d_cont .= '<div class="ee-admin-dialog-container-inner-content"></div>';
        $d_cont .= '</div>';
        echo $d_cont;
        // overlay
        $o_cont = '<div id="espresso-admin-page-overlay-dv" class=""></div>';
        echo $o_cont;
        EE_Registry::instance()->load_helper('Template');
        $template = EE_WPUSERS_TEMPLATE_PATH . 'eea-wp-users-login-form.template.php';
        EEH_Template::display_template($template);
    }


    /**
     * Callback for the process_login_form ajax action that handles logging a person in if their
     * credentials match.
     *
     * @since 1.0.0
     * @param array $login_args                 If included this is being called externally for processing.
     * @param bool  $handle_return              Used by external callers to indicate they'll take care of the
     *                                          return of data.
     * @return array response.
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function process_login_form($login_args = array(), $handle_return = true)
    {
        $success       = true;
        $field_input   = array();
        $login_args    = (array) $login_args;
        $handle_return = (bool) $handle_return;
        // first verify we have the necessary data.
        $user_login = isset($login_args['user_login'])
            ? $login_args['user_login']
            : EE_Registry::instance()->REQ->get('login_name');
        $user_pass  = isset($login_args['login_pass'])
            ? $login_args['login_pass']
            : EE_Registry::instance()->REQ->get('login_pass');
        $rememberme = isset($login_args['rememberme'])
            ? $login_args['rememberme']
            : EE_Registry::instance()->REQ->get('rememberme');
        if (empty($user_login)) {
            EE_Error::add_error(
                esc_html__('Missing a username.', 'event_espresso'),
                __FILE__,
                __FUNCTION__,
                __LINE__
            );
            $field_input[] = 'user_login';
            $success       = false;
        }
        if (empty($user_pass)) {
            EE_Error::add_error(
                esc_html__('Missing a password.', 'event_espresso'),
                __FILE__,
                __FUNCTION__,
                __LINE__
            );
            $field_input[] = 'user_pass';
            $success       = false;
        }
        if (! $success) {
            $return_data = array(
                'wp_user_response' => array(
                    'require_login'          => true,
                    'show_login_form'        => true,
                    'show_errors_in_context' => true,
                    'validation_error'       => array('field' => $field_input),
                ),
            );
            if ($handle_return) {
                self::_return_json($return_data);
            } else {
                return $return_data;
            }
        }
        // prevent other plugins from doing anything when users are logging in via the registration process
        remove_all_actions('wp_login');
        // validate user creds and login if successful
        $user = wp_signon(array(
            'user_login'    => $user_login,
            'user_password' => $user_pass,
            'remember'      => $rememberme,
        ));
        if (is_wp_error($user)) {
            $lost_password_link = EEH_HTML::link(
                esc_url(wp_lostpassword_url()),
                esc_html__('Lost your password?', 'event_espresso'),
                esc_attr__('Password Lost and Found', 'event_espresso'),
                '',
                'ee_user-lost-password'
            );
            EE_Error::add_error(
                sprintf(
                    esc_html__('Invalid username or incorrect password. %s', 'event_espresso'),
                    $lost_password_link
                ),
                __FILE__,
                __FUNCTION__,
                __LINE__
            );
            $return_data = array(
                'wp_user_response' => array(
                    'require_login'          => true,
                    'show_login_form'        => true,
                    'show_errors_in_context' => true,
                    'validation_error'       => array(
                        'field' => array('login_error_notice'),
                    ),
                ),
            );
        } else {
            EE_Error::add_success(
                sprintf(
                    esc_html__('Logged in successfully as %s!', 'event_espresso'),
                    $user->display_name
                )
            );
	
            $return_data = array(
                'wp_user_response' => array(
                    'require_login'          => false,
                    'show_login_form'        => false,
                    'show_errors_in_context' => false,
                ),
            );
        }
        if ($handle_return) {
            self::_return_json($return_data);
            // the above exits after echoing JSON
        }
        return $return_data;
    }


    /**
     * callback for AHEE__SPCO__load_reg_steps__reg_steps_to_load.
     * Take care of registering a login step IF the event requires it.
     *
     * @param array $reg_steps
     * @return array an array of reg step configuration
     */
    public static function register_login_reg_step($reg_steps)
    {
        array_unshift(
            $reg_steps,
            array(
                'file_path'  => EE_WPUSERS_PATH,
                'class_name' => 'EE_SPCO_Reg_Step_WP_User_Login',
                'slug'       => 'wpuser_login',
                'has_hooks'  => false,
            )
        );
        return $reg_steps;
    }


    /**
     * Callback for the 'ee_process_user_trouble_notification' ajax action.
     * We use this to send a notification to the event author that a registration is having trouble not receiving
     * emails.
     *
     * @return void (json object returned in a response).
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     * @throws EntityNotFoundException
     */
    public static function send_notification_to_admin()
    {
        // first check if there is required params in the request.
        $email        = isset($_POST['contact_email']) ? sanitize_email($_POST['contact_email']) : '';
        $reg_url_link = isset($_POST['reg_url_link']) ? esc_attr($_POST['reg_url_link']) : '';
        $default_return_data = $return_data = array(
            'wp_user_response' => array(
                'require_login'          => false,
                'show_login_form'        => false,
                'show_errors_in_context' => false,
            ),
        );
        if (! $email || ! $reg_url_link) {
            EE_Error::add_error(
                esc_html__('Invalid email or registration.  Unable to process', 'event_espresso'),
                __FILE__,
                __FUNCTION__,
                __LINE__
            );
            $return_data = $default_return_data;
            self::_return_json($return_data);
        }
        // try to get registration matching reg_url_link so we can fill out the dynamic data for the email.
        $registration = EEM_Registration::instance()->get_registration_for_reg_url_link($reg_url_link);
        // k we have what we need to send the notification
        $event        = $registration instanceof EE_Registration ? $registration->event() : null;
        $event_author = $event instanceof EE_Event ? $event->wp_user() : 0;
        $event_author = $event_author ? new WP_User($event_author) : null;
        $contact      = $registration instanceof EE_Registration ? $registration->attendee() : null;
        if (! $registration instanceof EE_Registration
            || ! $event_author instanceof WP_User
            || ! $contact instanceof EE_Attendee
        ) {
            EE_Error::add_error(
                esc_html__(
                    'Unable to process because valid registration could not be retrieved.',
                    'event_espresso'
                ),
                __FILE__,
                __FUNCTION__,
                __LINE__
            );
            $return_data = $default_return_data;
            self::_return_json($return_data);
        }
        $to      = apply_filters(
            'FHEE__EED_WP_Users_SPCO__send_notification_to_admin__to',
            $event_author->user_email,
            $registration
        );
        $subject = apply_filters(
            'FHEE__EED_WP_Users_SPCO__send_notification_to_admin__subject',
            esc_html__('User having trouble receiving emails', 'event_espresso'),
            $registration
        );
        $content = sprintf(esc_html__('Hi %s,', 'event_espresso'), $event_author->display_name);
        $content .= "\n\n";
        $content .= esc_html__(
            'There is a user having trouble with receiving emails for their recent regsitration.  You can follow up with them using the following information:',
            'event_espresso'
        );
        $content .= "\n\n";
        $content .= sprintf(esc_html__('Attendee Name: %s', 'event_espresso'), $contact->full_name()) . "\n";
        $content .= sprintf(
            esc_html__('Event Registered for: %s %s', 'event_espresso'),
            $event->name(),
            $event->get_admin_edit_link()
        );
        $content .= "\n";
        $content .= sprintf(
            esc_html__('Registration Details: %s', 'event_espresso'),
            $registration->get_admin_edit_url()
        );
        $content .= "\n";
        $content .= sprintf(
            esc_html__(
                'Email provided to contact them with (this was also set as the reply-to for this email): %s',
                'event_espresso'
            ),
            $email
        );
        $content .= "\n";
        $content .= sprintf(esc_html__('Sincerely, Event Espresso', 'event_espresso'));
        $message = apply_filters(
            'FHEE__EED_WP_Users_SPCO__send_notification_to_admin__message',
            $content,
            $registration
        );
        $headers = array('Reply-To:' . $email);
        $success = wp_mail($to, $subject, $message, $headers);
        if ($success) {
            EE_Error::add_success(
                apply_filters(
                    'FHEE__EED_WP_Users_SPCO__send_notification_to_admin__success_message',
                    esc_html__(
                        'Email successfully sent. You will hear from us as soon as possible.',
                        'event_espresso'
                    ),
                    $registration
                )
            );
        } else {
            EE_Error::add_error(
                apply_filters(
                    'FHEE__EED_WP_Users_SPCO__send_notification_to_admin__fail_message',
                    esc_html__(
                        'Email was not sent successfully.  There could be something wrong with our server. Please refresh the page and try again.',
                        'event_espresso'
                    ),
                    $registration
                )
            );
        }
        $return_data = $default_return_data;
        self::_return_json($return_data);
    }


    /**
     * This will auto login the registered user if the key for auto-login is in the request after a successful user
     * registration.
     *
     * @param int $user_id The user_id for the WP_User being logged in automatically.
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function auto_login_registered_user($user_id)
    {
        if (EE_Registry::instance()->REQ->get('ee_do_auto_login')) {
            wp_set_auth_cookie($user_id, false, false);
        }
    }


    /**
     * Callback for `register_form` WordPress hook.
     * If `ee_do_auto_login` is in the request then we add that as a hidden field in the registration form.
     *
     * @throws InvalidArgumentException
     * @throws InvalidDataTypeException
     * @throws InvalidInterfaceException
     */
    public static function add_auto_login_parameter()
    {
        if (EE_Registry::instance()->REQ->get('ee_do_auto_login')) {
            echo '<input type="hidden" name="ee_do_auto_login" value="1">';
            echo '<input type="hidden" name="ee_load_on_login" value="1">';
        }
    }


    protected static function _return_json($return_data = array())
    {
        $json = new EE_SPCO_JSON_Response();
        $json->set_return_data($return_data);
        echo $json;
        exit();
    }

    public static function add_parent_credit_input($before_payment_options)
    {
        $user_id = get_current_user_id();
        $parent_credit_balance = EE_WPUsers::get_credit_total_balance($user_id);

        if($parent_credit_balance == null || $parent_credit_balance == 0)
        {
            return $before_payment_options;
        }

        $cardInfo = self::get_card_info();
        list($first_item, $grand_total, $cart) = $cardInfo; 
        $first_parent_item = $first_item->parent();
		  
        $price = $first_parent_item->unit_price();

        $applicaple_parent_credit = min($price, $parent_credit_balance);


        $applied_credit_amount = EE_WPUsers::get_applied_credit_amount($user_id, $first_item->transaction()->ID(), 0);
        $parent_credit_disabled = $applied_credit_amount != null &&  $applied_credit_amount != 0;

        add_action('wp_enqueue_scripts',  'enqueue_scripts' );
        EE_Registry::instance()->load_helper('HTML');

        $singular_label = esc_html__('Usable Credit', 'event_espresso');

        $warning_message = $parent_credit_disabled ? "Your credit has been already applied. After credit application you can not use your coupon." : "If you have a coupon, please apply it first, otherwise
        you can not use your coupon after credit usage.";
        $parent_credit_balance = $parent_credit_disabled ? 0 : $parent_credit_balance;
        $before_payment_options->add_subsections(
           [
               'parent_credit_form' => new EE_Form_Section_Proper(
                   [
                       'layout_strategy' => new EE_No_Layout(['use_break_tags' => false]),
                       'subsections'     => [
                        'ee_parent_credit_warning_message' => new EE_Form_Section_HTML(EEH_HTML::p($warning_message)),
                           'ee_parent_credit_input_wrap_open' => new EE_Form_Section_HTML(
                               EEH_HTML::h5(
                                   $singular_label,
                                   '',
                                   'ees-parent-credit-header',
                                   'margin-bottom: .5rem;'
                               )
                               . EEH_HTML::div('', '', 'ees-parent-credit-input__wrapper')
                           ),
                        
                           'ee_parent_credit_input'  => new EE_Text_Input(
                               [
                                   'default'          => $applicaple_parent_credit,
                                   'html_id'          => 'ees-parent-credit-input',
                                   'html_class'       => 'ees-parent-credit-input ee-reg-qstn',
                                   'html_name'        => 'ee_parent_credit_input',
                                   'html_label_text'  => $singular_label,
                                   'html_label_class' => 'screen-reader-text',
                                   'disabled' => true
                               ]
                           ),
                       
                           'ee_parent_credit_submit' => new EE_Button_Input(
                               [
                                   'html_id'               => 'ees-parent-credit-submit',
                                   'html_name'             => 'ee_parent_credit_submit',
                                   'button_content'        => sprintf(esc_html__('Apply %s', 'event_espresso'), $singular_label),
                                   'other_html_attributes' => 'type="button"',
                                   'no_label'  => true,
                                   'disabled' => $parent_credit_disabled
                               ]
                           ),
                           'ee_parent_credit_input_wrap_close' => new EE_Form_Section_HTML(EEH_HTML::divx()),
                           'ee_parent_credit_header' => new EE_Form_Section_HTML(
                               EEH_HTML::div(' ', '', 'clear-float')
                           ),
                       ],
                   ]
               ),
           ]
       );

        return $before_payment_options;
    }

}
