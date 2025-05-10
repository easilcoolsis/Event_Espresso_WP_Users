<?php

if (! defined('ABSPATH')) {
    exit('No direct script access allowed');
}
/*
  Plugin Name:  AlphaStar Parent Users 
  Description: This adds the WP users integration.
  Version: 2.1.3.p
  Author: AlphaStar Academy
 */

define('EE_WPUSERS_VERSION', '2.1.3.p');
define('EE_WPUSERS_MIN_CORE_VERSION_REQUIRED', '4.8.21.rc.005');
define('EE_WPUSERS_PLUGIN_FILE', __FILE__);


function load_ee_core_wpusers()
{
    if (class_exists('EE_Addon')) {
        // new_addon version
        require_once(plugin_dir_path(__FILE__) . 'EE_WPUsers.class.php');

        EE_WPUsers::register_addon();
    }
    asa_parent_users_tables_creation();
}

add_action('AHEE__EE_System__load_espresso_addons', 'load_ee_core_wpusers');


function asa_parent_users_tables_creation()
{
    global $wpdb;
    $tableName= $wpdb->prefix."asa_parent_credit_history";
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $tableName (
        id int(7) NOT NULL AUTO_INCREMENT,
        registration_id int(10),
        transaction_id int(10),
        event_id bigint(20) NOT NULL,
        parent_user_id bigint(20) NOT NULL,
        credit_debit varchar(1) NOT NULL,
        course_price DECIMAL(18,2),
        credit_amount DECIMAL(18,2) NOT NULL,
        create_date DATETIME NOT NULL,  
        create_user_id varchar(50) NOT NULL,  
        update_date DATETIME,  
        update_user_id varchar(50),  
        description varchar(500), 
        status tinyint(1),        
        PRIMARY KEY (id)
    ) $charset_collate;";

   $wpdb->query($sql);

   $column_name = 'expire_date'; // Name of the column you want to add

   $column_exists = $wpdb->get_results(
    $wpdb->prepare(
        "SHOW COLUMNS FROM `$tableName` LIKE %s",
        $column_name
    )
   );

   if (empty($column_exists)) {

     $sql = "ALTER TABLE {$tableName} ADD(
       expire_date DATE
       )";
       $wpdb->query($sql);  
    }
    $column_name = 'office_note'; 

    $column_exists = $wpdb->get_results(
     $wpdb->prepare(
         "SHOW COLUMNS FROM `$tableName` LIKE %s",
         $column_name
     )
    );
 
    if (empty($column_exists)) {
        $sql = "ALTER TABLE {$tableName} ADD(
            office_note varchar(500) 
        )";
        $wpdb->query($sql);  
   }

   $column_name = 'debit_ref_id'; // Name of the column you want to add

   $column_exists = $wpdb->get_results(
    $wpdb->prepare(
        "SHOW COLUMNS FROM `$tableName` LIKE %s",
        $column_name
    )
   );

   if (empty($column_exists)) {
   $sql = "ALTER TABLE {$tableName} ADD(
    debit_ref_id int(7)
    )";
    $wpdb->query($sql); 
   } 

   }


add_action('show_user_profile','add_credit_user_profile_field');
add_action('edit_user_profile',  'add_credit_user_profile_field');

add_action('personal_options_update', 'save_credit_user_profile_field');
add_action('edit_user_profile_update', 'save_credit_user_profile_field');



 function add_credit_user_profile_field($user) {
	 if(!current_user_can('administrator')) {
		 return;
	 }
	 
 ?>
 <h3><?php _e("Parent Credit Balance", "your_textdomain"); ?></h3>


 <table class="form-table">
     <tr>
         <th><label for="credit_balance"><?php _e("Credit Balance", "your_textdomain"); ?></label></th>
         <td>
             <input type="text"    readonly  name="credit_balance" id="credit_balance" value="<?php echo  '$'.esc_attr(EE_WPUsers::get_credit_total_balance($user->ID)); ?>" class="regular-text" />
         </td>
         <th><label for="debit_ref_id"><?php _e("Debit Ref ID", "your_textdomain"); ?></label></th>
         <td>
				<select  name='debit_ref_id' id="debit_ref_id">
				<option value=""> </option>
				<?php
					$debit_ref_ids = EE_WPUsers::get_debit_ref_ids($user->ID);
					foreach($debit_ref_ids as $debit_ref_id) {
						echo '<option value="' . $debit_ref_id["id"] . '" >'. $debit_ref_id["id"]. '- $'.$debit_ref_id["credit_amount"] . '</option>';
					}
				?>
				</select>
                <p style="font-weight: bold;">Please enter the Debit Ref ID, if you enter a credit amount < 0 </p>
		   
           </td>
       </tr>
       <tr>
         <th><label for="parent_credit_amount"><?php _e("Add/Remove Credit $", "your_textdomain"); ?></label></th>
         <td>
             <input type="number"  step="0.01" name="parent_credit_amount" id="parent_credit_amount" value="" class="regular-text" />

         </td>
         <th><label for="expire_date"><?php _e("Expiry Date", "your_textdomain"); ?></label></th>
         <td>
             <input type="date"  name="expire_date" id="expire_date" value="" class="regular-text" />

         </td>
       
       </tr>
       <tr>
         <th><label for="credit_description"><?php _e("Credit Description", "your_textdomain"); ?></label></th>
         <td>
            <textarea  name="credit_description" id="credit_description"  value="" rows="5" cols="20"></textarea>
         </td>
         <th><label for="office_note"><?php _e("Internal Note", "your_textdomain"); ?></label></th>
        <td>
        <textarea id="office_note" name="office_note" value="" rows="5" cols="20"></textarea>
         </td>
     </tr>
 </table>
 <?php
}

 function save_credit_user_profile_field($user_id) {
    // Check if current user has permission to edit

   if(!current_user_can('administrator', $user_id)) {
        return false;
    }

    if (isset($_POST['parent_credit_amount'])) {

        if ($_POST['parent_credit_amount'] == 0) {
            return false;
        }

        $credit_debit = $_POST['parent_credit_amount'] > 0 ? 'D' :'C';
        $debit_ref_id = $credit_debit == "C" ? $_POST['debit_ref_id'] : null;
        if($credit_debit  == 'C' &&  $debit_ref_id == "")
        {
           return false;
        }
        if($credit_debit  == 'D')
        {
            $debit_ref_id = null;
        }
        $credit_balance = EE_WPUsers::get_credit_total_balance($user_id);

        if($credit_balance + $_POST['parent_credit_amount'] < 0)
        {
            echo 'The credit balance can not be less then 0.';
            return;
        }

        $expire_date = $credit_debit  == 'C' ? null : ($_POST['expire_date'] == "" ? null : date("Y-m-d", strtotime($_POST['expire_date'])));
        
        EE_WPUsers::add_credit_history(true, $_POST['credit_description'],$_POST['parent_credit_amount'], $user_id, 1, 0, 0,  $credit_debit, 0, 0, $_POST['office_note'], $debit_ref_id, $expire_date, 0);
    }
}
