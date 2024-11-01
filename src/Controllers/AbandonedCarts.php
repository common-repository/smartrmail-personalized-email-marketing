<?php

namespace SmartrMail\Controllers;

use SmartrMail\Services\ProductHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCarts {
    
    protected $usermeta;
    
    protected $cartURL;
    
    protected $tokens;
    
    protected $timeAbandoned;

    public function __construct( $usermeta, $cartURL, $tokens, $timeAbandoned ) {
        $this->usermeta = $usermeta;
        $this->cartURL = $cartURL;
        $this->tokens = $tokens;
        $this->timeAbandoned = $timeAbandoned;
        
        $this->RegisterEndpoints();
        
        add_action('woocommerce_cart_emptied', [$this, 'ClearAbandonedCartMetadata']);
        add_action('woocommerce_add_to_cart', [$this, 'AbandonedCartTimestamp']);
        add_action('woocommerce_cart_item_removed', [$this, 'AbandonedCartTimestamp']);
        add_action('woocommerce_cart_item_restored', [$this, 'AbandonedCartTimestamp']);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'AbandonedCartTimestamp']);
        add_action('woocommerce_thankyou', [$this, 'NewOrderCreated']);
    }
    
    public function Init() {
        
    }

    public function NewOrderCreated($order_id) {
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        $this->cartURL->DeleteActiveCarts($user_id);  
    }
  
    public function AbandonedCartTimestamp() {
        $id = get_current_user_id();
        $cart_content = get_user_meta($id, '_woocommerce_persistent_cart_1');
        $this->cartURL->UpdateAbandonedCartData($id, $cart_content);
    }
    
    public function ClearAbandonedCartMetadata( $id ) {
        //update_user_meta($id, $this->usermeta['cart.url'], '');
    }
    
    public function RegisterEndpoints() {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'swi-api/v3', '/abandoned-carts', [
                'methods' => 'GET',
                'args' => [
                    'last_update_max'
                ],
                'callback' => [$this, 'GetAbandonedCarts'],
            ] );
            
            register_rest_route( 'swi-api/v3', '/delete-abandoned', [
                'methods' => 'DELETE',
                'callback' => [$this, 'DeleteAbandonedCarts'],

            ] );
        } );
    }
    
    public function GetAbandonedCarts( ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'swi_abandoned_carts';
        $users_table = $wpdb->prefix . 'users';
        $usermeta_table = $wpdb->prefix . 'usermeta';

        $last_update_max = time() - (60 * 60); // 60 minutes ago

        if(isset($_GET['last_update_max'])) {
            $last_update_max = $_GET['last_update_max'];
        }
        
        $sql = "SELECT * FROM $table_name" .
            " JOIN $users_table ON ($table_name.user_id = $users_table.ID)" .
            " WHERE timestamp <= '{$last_update_max}'";
        write_log($sql);
        $data = $wpdb->get_results($sql);

        $json = array();

        foreach ($data as $value) {
            $cart = array(
                      'id' => $value->id,
                      'email' => $value->user_email,
                      'subscriber_uid' => $value->user_id,
                      'first_name' => get_user_meta($value->user_id, 'first_name', true),
                      'last_name' => get_user_meta($value->user_id, 'last_name', true),
                      'cart_url' => add_query_arg( 'fill_cart', $value->cart_secret, wc_get_cart_url() ),
                      'timestamp' => $value->timestamp,
                      'items' => unserialize($value->cart_meta)
                    );
            $json[] = $cart;
        }

        return wp_send_json($json);
    }
    
    public function DeleteAbandonedCarts( $params ) {
        $cartSecret = $params['cart_secret'];
        
        $abandonedCart = $this->cartURL->GetAbandonedCartData( $cartSecret );
        if ( ! $abandonedCart ) {
            return false;
        }
        
        return $this->cartURL->DeleteAbandonedCartData( $cartSecret );
    }
    
    
}
