<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

if (!function_exists(powerwaf_deactivate())) {
    include "powerwaf.php";
    powerwaf_deactivate();
}

//$option_name = 'wporg_option';
//delete_option($option_name);