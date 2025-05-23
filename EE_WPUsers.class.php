<?php

// define constants
define('EE_WPUSERS_PATH', plugin_dir_path(__FILE__));
define('EE_WPUSERS_URL', plugin_dir_url(__FILE__));
define('EE_WPUSERS_TEMPLATE_PATH', EE_WPUSERS_PATH . 'templates/');
define('EE_WPUSERS_BASENAME', plugin_basename(EE_WPUSERS_PLUGIN_FILE));

/**
 * Class definition for the EE_WPUsers object
 *
 * @since        1.0.0
 * @package      EE WPUsers
 */
class EE_WPUsers extends EE_Addon
{

    /**
     * Set up
     */
    public static function register_addon()
    {
        $registration_array = array(
            'version'          => EE_WPUSERS_VERSION,
            'min_core_version' => EE_WPUSERS_MIN_CORE_VERSION_REQUIRED,
            'main_file_path'   => EE_WPUSERS_PLUGIN_FILE,
            'config_class'     => 'EE_WPUsers_Config',
            'config_name'      => 'user_integration',
            'admin_callback'   => 'additional_admin_hooks',
            'module_paths'     => array(
                EE_WPUSERS_PATH . 'EED_WP_Users_SPCO.module.php',
                EE_WPUSERS_PATH . 'EED_WP_Users_Admin.module.php',
                EE_WPUSERS_PATH . 'EED_WP_Users_Ticket_Selector.module.php',
            ),
            'dms_paths'        => array(EE_WPUSERS_PATH . 'core/data_migration_scripts'),
            'autoloader_paths' => array(
                'EE_WPUsers_Config'              => EE_WPUSERS_PATH . 'EE_WPUsers_Config.php',
                'EE_SPCO_Reg_Step_WP_User_Login' => EE_WPUSERS_PATH . 'EE_SPCO_Reg_Step_WP_User_Login.class.php',
                'EE_DMS_2_0_0_user_option'       =>
                    EE_WPUSERS_PATH
                    . 'core/data_migration_scripts/2_0_0_stages/EE_DMS_2_0_0_user_option.dmsstage.php',
            ),
            // if plugin update engine is being used for auto-updates. not needed if PUE is not being used.
            'pue_options'      => array(
                'pue_plugin_slug' => 'eea-wp-user-integration',
                'checkPeriod'     => '24',
                'use_wp_update'   => false,
            ),
            'namespace' => array(
                'FQNS' => 'EventEspresso\WpUser',
                'DIR' => __DIR__,
            ),
        );
        // the My Events Shortcode registration depends on EE version.
        if (EE_Register_Addon::_meets_min_core_version_requirement('4.9.46.rc.024')) {
            // register shortcode for new system.
            $registration_array['shortcode_fqcns'] = array(
                'EventEspresso\WpUser\domain\entities\shortcodes\EspressoMyEvents'
            );
        } else {
            // register shortcode for old system.
            $registration_array['shortcode_paths'] = array(
                EE_WPUSERS_PATH . 'EES_Espresso_My_Events.shortcode.php',
            );
        }
        // register addon via Plugin API
        EE_Register_Addon::register('EE_WPUsers', $registration_array);
    }


    /**
     * Register things that have to happen early in loading.
     */
    public function after_registration()
    {
        $this->register_dependencies();
    }

    protected function register_dependencies()
    {
        EE_Dependency_Map::register_dependencies(
            'EventEspresso\WpUser\domain\entities\shortcodes\EspressoMyEvents',
            array(
                'EventEspresso\core\services\cache\PostRelatedCacheManager' => EE_Dependency_Map::load_from_cache,
                'EE_Request' => EE_Dependency_Map::load_from_cache,
                'EEM_Event' => EE_Dependency_Map::load_from_cache,
                'EEM_Registration' => EE_Dependency_Map::load_from_cache
            )
        );
    }


    /**
     *  additional admin hooks
     */
    public function additional_admin_hooks()
    {
        if (is_admin() && ! EE_Maintenance_Mode::instance()->level()) {
            add_filter('plugin_action_links', array($this, 'plugin_actions'), 10, 2);
        }
    }


    /**
     * plugin_actions
     * Add a settings link to the Plugins page, so people can go straight from the plugin page to the settings page.
     *
     * @param $links
     * @param $file
     * @return array
     */
    public function plugin_actions($links, $file)
    {
        if ($file === EE_WPUSERS_BASENAME) {
            array_unshift(
                $links,
                '<a href="admin.php?page=espresso_registration_form&action=wp_user_settings">'
                . __('Settings', 'event_espresso')
                . '</a>'
            );
        }
        return $links;
    }



    /**
     * Used to get a user id for a given EE_Attendee id.
     * If none found then null is returned.
     *
     * @param int $att_id The attendee id to find a user match with.
     * @return int|null     $user_id if found otherwise null.
     */
    public static function get_attendee_user($att_id)
    {
        global $wpdb;
        $key     = $wpdb->get_blog_prefix() . 'EE_Attendee_ID-'.$att_id;
        $query   = "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '$key' AND meta_value = '%d'";
        $user_id = $wpdb->get_var($wpdb->prepare($query, (int) $att_id));
        return $user_id ? (int) $user_id : null;
    }


    /**
     * used to determine if forced login is turned on for the event or not.
     *
     * @param int|EE_Event $event Either event_id or EE_Event object.
     * @return bool true YES forced login turned on false NO forced login turned off.
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function is_event_force_login($event)
    {
        return self::_get_wp_user_event_setting('force_login', $event);
    }


    public static function is_auto_user_create_on($event)
    {
        return self::_get_wp_user_event_setting('auto_create_user', $event);
    }


    public static function default_user_create_role($event)
    {
        return self::_get_wp_user_event_setting('default_wp_user_role', $event);
    }


    /**
     * This retrieves the specific wp_user setting for an event as indicated by key.
     *
     * @param string $key           What setting are we retrieving
     * @param        $event
     * @return mixed Whatever the value for the key is or what is set as the global default if it doesn't
     *                              exist.
     * @throws EE_Error
     * @throws ReflectionException
     * @internal param EE_Event|int $EE_Event or event id
     */
    protected static function _get_wp_user_event_setting($key, $event)
    {
        // any global defaults?
        $config         = isset(EE_Registry::instance()->CFG->addons->user_integration)
            ? EE_Registry::instance()->CFG->addons->user_integration
            : false;
        $global_default = array(
            'force_login'          => $config && isset($config->force_login) ? $config->force_login : false,
            'auto_create_user'     => $config && isset($config->auto_create_user) ? $config->auto_create_user : false,
            'default_wp_user_role' => $config && isset($config->default_wp_user_role)
                ? $config->default_wp_user_role
                : 'subscriber',
        );


        $event    = $event instanceof EE_Event
            ? $event
            : EE_Registry::instance()->load_model('Event')->get_one_by_ID((int) $event);
        $settings = $event instanceof EE_Event
            ? $event->get_post_meta('ee_wpuser_integration_settings', true)
            : array();
        if (! empty($settings)) {
            $value = isset($settings[ $key ]) ? $settings[ $key ] : $global_default[ $key ];

            // since post_meta *might* return an empty string.  If the default global value is boolean, then let's make
            // sure we cast the value returned from the post_meta as boolean in case its an empty string.
            return is_bool($global_default[ $key ]) ? (bool) $value : $value;
        }
        return $global_default[ $key ];
    }


    /**
     * used to update the force login setting for an event.
     *
     * @param int|EE_Event $event       Either the EE_Event object or int.
     * @param bool         $force_login value.  If turning off you can just not send.
     * @return mixed (via downstream activity)
     * @throws EE_Error (via downstream activity)
     * @throws ReflectionException
     */
    public static function update_event_force_login($event, $force_login = false)
    {
        return self::_update_wp_user_event_setting('force_login', $event, $force_login);
    }


    /**
     * used to update the auto create user setting for an event.
     *
     * @param int|EE_Event $event       Either the EE_Event object or int.
     * @param bool         $auto_create value.  If turning off you can just not send.
     * @return mixed (via downstream activity)
     * @throws EE_Error (via downstream activity)
     * @throws ReflectionException
     */
    public static function update_auto_create_user($event, $auto_create = false)
    {
        return self::_update_wp_user_event_setting('auto_create_user', $event, $auto_create);
    }


    public static function update_default_wp_user_role($event, $default_role = 'subscriber')
    {
        return self::_update_wp_user_event_setting('default_wp_user_role', $event, $default_role);
    }


    /**
     * used to update the wp_user event specific settings.
     *
     * @param string       $key   What setting is being updated.
     * @param int|EE_Event $event Either the EE_Event object or id.
     * @param mixed        $value The value being updated.
     * @return mixed Returns meta_id if the meta doesn't exist, otherwise returns true on success
     *                            and false on failure. NOTE: If the meta_value passed to this function is the
     *                            same as the value that is already in the database, this function returns false.
     * @throws EE_Error
     * @throws ReflectionException
     */
    protected static function _update_wp_user_event_setting($key, $event, $value)
    {
        $event = $event instanceof EE_Event
            ? $event
            : EE_Registry::instance()->load_model('Event')->get_one_by_ID((int) $event);

        if (! $event instanceof EE_Event) {
            return false;
        }
        $settings       = $event->get_post_meta('ee_wpuser_integration_settings', true);
        $settings       = empty($settings) ? array() : $settings;
        if(isset($settings[ $key ]))
        $settings[ $key ] = $value;
        return $event->update_post_meta('ee_wpuser_integration_settings', $settings);
    }

    public static function get_credit_total_balance($userId)
    {
         global $wpdb;
         $tableName= $wpdb->prefix."asa_parent_credit_history";
 
         $query = "SELECT IFNULL(SUM(x.credit_amount),0) FROM
                     (SELECT SUM(CASE WHEN credit_debit = 'D' THEN credit_amount ELSE credit_amount*-1 END) credit_amount
                       FROM $tableName
                   WHERE parent_user_id = $userId
                     AND status = 1
                    AND (expire_date is null or expire_date >= CURDATE())
                    GROUP BY IFNULL(debit_ref_id, id)
                    HAVING SUM(CASE WHEN credit_debit = 'D' THEN credit_amount ELSE credit_amount*-1 END) > 0) x";
 
         $creditBalance = $wpdb->get_var($query);
 
         return $creditBalance;
 
     }
 
     public static function get_parent_credit_history($userId)
     {
          global $wpdb;
          $tableName= $wpdb->prefix."asa_parent_credit_history";
  
          $query = "SELECT description, credit_debit, course_price, credit_amount, create_date , expire_date
                      FROM $tableName
                  WHERE parent_user_id = $userId
                      AND status = 1
                      order by create_date";
  
          $history = $wpdb->get_results($query, 'ARRAY_A');
            
          return $history;
      }

     public static function get_applied_credit_amount($userId, $txn_id)
     {
          global $wpdb;
          $tableName= $wpdb->prefix."asa_parent_credit_history";
  
          $query = "SELECT credit_amount 
                      FROM $tableName
                  WHERE parent_user_id = $userId
                      AND transaction_id = $txn_id";
  
          $appliedCreditAmount = $wpdb->get_var($query);
  
          return $appliedCreditAmount;
      }
      
      public static function get_unexpired_debit_records($userId)
      {
           global $wpdb;
           $tableName= $wpdb->prefix."asa_parent_credit_history";
           $query = "SELECT expire_date, h.id, x.credit_amount
           FROM  $tableName h ,
           (SELECT IFNULL(debit_ref_id, id) id,  SUM(CASE WHEN credit_debit = 'D' THEN credit_amount ELSE credit_amount*-1 END) credit_amount
                                  FROM $tableName
                              WHERE parent_user_id = $userId
                                AND status = 1
                               AND (expire_date is null or expire_date >= CURDATE())
                               GROUP BY IFNULL(debit_ref_id, id)
                               HAVING SUM(CASE WHEN credit_debit = 'D' THEN credit_amount ELSE credit_amount*-1 END) > 0 ) x
                               WHERE x.id = h.id
                              ORDER BY IFNULL(expire_date,'9999-12-12'), h.id";
   
           $unexpiredDebitRecords = $wpdb->get_results($query, 'ARRAY_A');
   
           return $unexpiredDebitRecords;
      }

      public static function get_debit_ref_ids($userId)
      {

        global $wpdb;
        $tableName= $wpdb->prefix."asa_parent_credit_history";
        $query = "SELECT  IFNULL(debit_ref_id, id) id, SUM(CASE WHEN credit_debit = 'D' THEN credit_amount ELSE credit_amount*-1 END) credit_amount
                    FROM  $tableName
                    WHERE status = 1
                     AND parent_user_id = $userId
                     AND (expire_date is null or expire_date >= CURDATE())
                    GROUP BY IFNULL(debit_ref_id, id)
                   HAVING SUM(CASE WHEN credit_debit = 'D' THEN credit_amount ELSE credit_amount*-1 END) > 0
                   ORDER BY  IFNULL(debit_ref_id, id) desc";

        $debitRefIds = $wpdb->get_results($query, 'ARRAY_A');
        
        return $debitRefIds;           

      }

      public static function update_parent_credit_history($userId, $txn_id)
      {
           date_default_timezone_set('America/Los_Angeles');
           global $wpdb;
           $tableName= $wpdb->prefix."asa_parent_credit_history";
           $updateDate = date("y-m-d H:i:s");
           
           $query = "UPDATE $tableName
                      SET STATUS = 1,
                      UPDATE_DATE = '$updateDate',
                      UPDATE_USER_ID = $userId
                   WHERE parent_user_id = $userId
                       AND transaction_id = $txn_id
                       AND status = 0";
   
           $appliedCreditAmount = $wpdb->get_var($query);
           date_default_timezone_set('UTC');    
           return $appliedCreditAmount;
       }

       public static function delete_parent_credit_history($userId, $txn_id)
       {
            global $wpdb;
            $tableName= $wpdb->prefix."asa_parent_credit_history";
            
            $query = "DELETE FROM $tableName
                    WHERE parent_user_id = $userId
                        AND transaction_id = $txn_id
                        AND status = 0";
    
            $results = $wpdb->get_results($query); 
            return $results;
        }

    public static  function add_credit_history(
        $admin,
        $description, 
        $credit_amount, 
        $parent_user_id, 
        $status, 
        $event_id, 
        $reg_id, 
        $credit_debit, 
        $course_price,
        $txn_id,
        $office_note,
        $debit_ref_id,
        $expire_date,
        $total_credit_amount)
 {
    if($credit_debit == 'C' &&  $admin == false)
    {
        $appliedCreditAmount = self::get_applied_credit_amount( $parent_user_id, $txn_id );
        if($appliedCreditAmount >= $total_credit_amount)
        {
            return;
        }
    }
     date_default_timezone_set('America/Los_Angeles');
           
     global $wpdb;
     $tableName= $wpdb->prefix."asa_parent_credit_history";
     $current_user_id = get_current_user_id();
   
     $wpdb->insert(
             $tableName,
             array(
                 'parent_user_id' => $parent_user_id,
                 'description' => $description,
                 'credit_debit' => $credit_debit,
                 'credit_amount' => abs($credit_amount),
                 'create_date' => date("y-m-d H:i:s") ,
                 'create_user_id' => $current_user_id,
                 'event_id' => $event_id,
                 'status' => $status,
                 'registration_id' => $reg_id,
                 'course_price' => $course_price,
                 'transaction_id'=> $txn_id,
                 'office_note' => $office_note,
                 'debit_ref_id' => $debit_ref_id,
                 'expire_date' => $expire_date
             )
         );
    date_default_timezone_set('UTC');     
 }
}
