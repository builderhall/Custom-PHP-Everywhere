<?php
/*
Plugin Name: Custom PHP Everywhere
Plugin URI: 
Description: A plugin that allows you to add custom PHP code to your posts and pages using shortcodes
Version: 1.0
Author: 
Author URI: 
License: GPL2
*/

// Create the custom_php_scripts table in the database when the plugin is activated
function custom_php_everywhere_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_php_scripts';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        script text NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'custom_php_everywhere_activate' );

// Handle the custom_php shortcode
function custom_php_everywhere_shortcode( $atts ) {
    // Check if user is logged in and has the 'execute_php' capability
    if ( is_user_logged_in() && current_user_can( 'execute_php' ) ) {
        // Retrieve the custom PHP script from the database
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_php_scripts';
        $id = (int) $atts['id'];
        $script = $wpdb->get_var( $wpdb->prepare( "SELECT script FROM $table_name WHERE id = %d", $id ) );
        // Execute the script
        ob_start();
        eval( '?>' . $script );
        $output = ob_get_clean();
        return $output;
    }
    // Return an error message if the user is not logged in or does not have the necessary capability
    return '<p>You do not have permission to execute PHP code.</p>';
}
add_shortcode( 'custom_php', 'custom_php_everywhere_shortcode' );

// Create the plugin's admin menu and page
function custom_php_everywhere_admin_menu() {
    add_menu_page(
        'Custom PHP Everywhere',
        'Custom PHP Everywhere',
        'manage_options',
        'custom-php-everywhere',
        'custom_php_everywhere_admin_page',
        '',
        99
    );
    add_submenu_page(
        'custom-php-everywhere',
        'Custom PHP Scripts',
        'Custom PHP Scripts',
        'manage_options',
        'custom-php-scripts',
        'custom_php_scripts_admin_page'
    );
}
add_action( 'admin_menu', 'custom_php_everywhere_admin_menu' );

// Render the custom PHP scripts admin page
