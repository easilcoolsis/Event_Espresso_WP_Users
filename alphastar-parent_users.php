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
}

add_action('AHEE__EE_System__load_espresso_addons', 'load_ee_core_wpusers');