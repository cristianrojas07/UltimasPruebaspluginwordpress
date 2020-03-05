<?php

define( 'LOGINCUST_VERSION', '2.0.1' );
define( 'LOGINCUST_FREE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOGINCUST_FREE_URL', plugin_dir_url( __FILE__ ) );

require_once( LOGINCUST_FREE_PATH . 'setup.php' );
require_once( LOGINCUST_FREE_PATH . 'inc/include-page-template.php' );
require_once( LOGINCUST_FREE_PATH . 'inc/customizer/customizer.php' );

/**
 * Add link to Login Customizer in Appearances menu
 */
function logincust_admin_link() {

	// Get global submenu
	global $submenu;
	global $url_customizer;
	// Generate the redirect url.
	$options = get_option( 'login_customizer_settings', array() );

	$url = add_query_arg(
		array(
			'autofocus[panel]' => 'logincust_panel',
			'url' => rawurlencode( get_permalink( $options['page'] ) ),
		),
		admin_url( 'customize.php' )
	);

	// Add Login Customizer as a menu item
	//$submenu['themes.php'][] = array( 'Login Customizer', 'manage_options', $url, 'login-customizer' );
	$url_customizer = $url;
}

add_action( 'admin_menu', 'logincust_admin_link' );
