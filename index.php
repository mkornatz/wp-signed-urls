<?php
/*
  Plugin Name: Signed URLs
  Description: Control access to a site using secure URLs which can only be generated with a secret key.
  Author: Matt Kornatz
  Version: 1.0.0
  Author URI: https://github.com/mkornatz
*/

// Don't call this file directly.
if ( ! class_exists( 'WP' ) ) {
	die();
}

define( 'SIGNED_URLS_PLUGIN_VERSION', '1.0.0' );

if ( ! defined( 'SIGNED_URLS_PLUGIN_PATH' ) ) {
  define( 'SIGNED_URLS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SIGNED_URLS_PLUGIN_BASENAME' ) ) {
  define( 'SIGNED_URLS_PLUGIN_BASENAME', plugin_basename(__FILE__) );
}

require_once( SIGNED_URLS_PLUGIN_PATH . 'signed-urls-options.php' );
$options = new Signed_Urls_Options();

if ( is_admin() ) {
  $options->register_with_wordpress_admin();

  function signed_urls_uninstall() {
    global $options;
    $options->delete_from_wordpress();
  }
  register_uninstall_hook( __FILE__, 'signed_urls_uninstall' );

} else {
  require_once( SIGNED_URLS_PLUGIN_PATH . 'signed-urls-front-end.php' );
  new Signed_Urls_Front_End($options);
}

function signed_urls_register_text_domain() {
  load_plugin_textdomain( 'signed-urls' );
}
add_action( 'plugins_loaded', 'signed_urls_register_text_domain' );

