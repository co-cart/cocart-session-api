<?php
/**
 * This file is designed to be used to load as package NOT a WP plugin!
 *
 * @version 1.0.0
 * @package CoCart Session API Package
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'COCART_SESSIONAPI_PACKAGE_FILE' ) ) {
	define( 'COCART_SESSIONAPI_PACKAGE_FILE', __FILE__ );
}

// Include the main CoCart Session API Package class.
if ( ! class_exists( 'CoCart\SessionAPI\Package', false ) ) {
	include_once untrailingslashit( plugin_dir_path( COCART_SESSIONAPI_PACKAGE_FILE ) ) . '/includes/class-cocart-session-api.php';
}

/**
 * Returns the main instance of cocart_session_api_package and only runs if it does not already exists.
 *
 * @return cocart_session_api_package
 */
if ( ! function_exists( 'cocart_session_api_package' ) ) {
	function cocart_session_api_package() {
		return \CoCart\SessionAPI\Package::init();
	}

	cocart_session_api_package();
}
