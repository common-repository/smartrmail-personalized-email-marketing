<?php 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
    'path' => plugin_dir_path( __FILE__ ),
    'uri' => plugin_dir_url( __FILE__ ),
    'vendor.path' => sprintf( '%svendor', plugin_dir_path( __FILE__ ) ),
    'views.path' => sprintf( '%sviews', plugin_dir_path( __FILE__ ) ),
    'assets.path' => sprintf( '%sassets', plugin_dir_path( __FILE__ ) ),
    'build.uri' => sprintf( '%sbuild', plugin_dir_url( __FILE__ ) ),
    'register.url' => 'https://go.smartrmail.com/woocommerce/register',
    'usermeta' => [
        'timestamp' => "swi_abandoned_cart",
        'cart.url' => 'swi_cart_url'
    ],
    'options.name' => [
        'id' => 'swi_user_id',
        'return.url' => 'swi_login_url',
        'javascript' => 'swi_javascript_javascript',
    ],
    'time.abandoned' => 60, 
];
