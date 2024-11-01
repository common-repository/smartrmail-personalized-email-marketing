<?php

/*
* Plugin Name: SmartrMail - Email Marketing for WooCommerce
* Description: Allows to track users activity within SmartrMail APP
* Version: 2.2.6
* Author: SmartrMail
* Author URI: https://smartrmail.com
* */

require_once __DIR__ . '/vendor/autoload.php';

use SmartrMail\Controllers;
use SmartrMail\Dependencies\Providers;
use SmartrMail\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$container = new Pimple\Container;

$container['config'] = require_once __DIR__ . '/config.php';

$container->register( new Providers\ProductHelperServiceProvider() );
$container->register( new Providers\TokensProvider() );
$container->register( new Providers\Controllers\AbandonedCartsProvider() );
$container->register( new Providers\Controllers\AdminControllerProvider() );
$container->register( new Providers\Controllers\CartLinkControllerProvider() );

add_action('wp_loaded', function() use ( $container ) {
    $container[ Controllers\AdminController::class ]->Init();
    $container[ Controllers\AbandonedCarts::class ]->Init();
    $container[ Controllers\CartLinkController::class ]->Init();
    add_action('wp_enqueue_scripts', 'popup_scripts');

    if (!function_exists('write_log')) {

        function write_log($log) {
            if (true === WP_DEBUG) {
                if (is_array($log) || is_object($log)) {
                    error_log(print_r($log, true));
                } else {
                    error_log($log);
                }
            }
        }

    }
    add_action('wp_enqueue_scripts', 'delete_abandoned_carts');
});

add_action( 'rest_api_init', function () {
  include_once('src/Controllers/CustomersController.php');
  include_once('src/Controllers/ProductsController.php');

  $controllers = array('CustomersController', 'ProductsController');
  foreach ( $controllers as $controller ) {
    $controller = new $controller();
    $controller->register_routes();
  }
});

function popup_scripts() {
  wp_register_script( 'smartrmail-popup', plugins_url( 'assets/js/smartrmail_popup.js', __FILE__ ), array('jquery'), null, true);
  $url = parse_url( get_home_url( ) );
  wp_localize_script( 'smartrmail-popup', 'WooCommerce', array( 'shop' => $url['host'] ));
  wp_enqueue_script( 'smartrmail-popup' );
}

function delete_abandoned_carts() {
    // Exit if the work has already been done.
    if ( get_option( 'swi_cart_migration_1', '0' ) == '1' ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'swi_abandoned_carts';
    $wpdb->query("DELETE FROM $table_name");

    // Add or update the wp_option
    update_option( 'swi_cart_migration_1', '1' );
}

global $db_version;
$db_version = '1.0';

function db_install() {
  global $wpdb;
  global $db_version;

  $table_name = $wpdb->prefix . 'swi_abandoned_carts';

  $charset_collate = $wpdb->get_charset_collate();

  if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
    $sql = "CREATE TABLE " . $table_name . " (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      timestamp int(10) NOT NULL,
      cart_secret text NOT NULL,
      cart_meta text NOT NULL,
      user_id bigint(20) unsigned NOT NULL,
      FOREIGN KEY (user_id) REFERENCES $wpdb->users(ID),
      PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'db_version', $db_version );
  }
}

register_activation_hook( __FILE__, 'db_install' );
