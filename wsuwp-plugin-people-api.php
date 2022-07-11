<?php
/**
 * Plugin Name: WSUWP People API
 * Plugin URI: https://github.com/wsuwebteam/wsuwp-plugin-people-api
 * Description: Add a custom API endpoint for retrieving profiles from the people directory.
 * Version: 1.1.0
 * Requires PHP: 7.0
 * Author: Washington State University, Dan White
 * Author URI: https://web.wsu.edu/
 * Text Domain: wsuwp-plugin-people-api
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Initiate plugin
require_once __DIR__ . '/includes/plugin.php';
