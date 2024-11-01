<?php
namespace SmartrMail\Controllers;

use SmartrMail\Services\ProductHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CartLinkController {
	protected $version = '1.1.2';
  protected $productHelper;
  protected $tokens;
  protected $timeAbandoned;

  public function __construct( $productHelper, $tokens, $timeAbandoned ) {
    //Does WooCommerce exists ?
    if ( ! class_exists('WC_Cart') ) {
      return;
    }

    $this->productHelper = $productHelper;
    $this->check_url();
    $this->tokens = $tokens;
    $this->timeAbandoned = $timeAbandoned;

    add_action('woocommerce_checkout_update_order_meta', [$this, 'add_referral_meta']);

    //Shop manager hooks
    if ( ! is_ajax() && current_user_can( 'manage_woocommerce' ) ) {
      //Admin
      add_action( 'woocommerce_cart_actions', [$this, 'woocommerce_cart_actions'] );
    }
  }

  public function Init() {

  }

	/**
	 * Add 'Link to this cart' to the cart actions
	 */
	public function woocommerce_cart_actions() {
		$url = $this->get_fill_cart_url();

		if ( $url != false ) {
			echo '<div class="soft79_fill_cart_url">' 
			. __( 'Link to this cart:', 'soft79_wccl' ) 
			. ' <input type="text" value="' . esc_url( $url ) . '"></div>';
		}
	}

	/**
	 * Return the url with &fill_cart= added
	 * 
	 * @param string (Optional) The base url. If omitted the url to the cart page will be used.
	 * @return string|false The url, or false if the cart is empty
	 */
	public function get_fill_cart_url( $base_url = false ) {
		$cart_contents = WC()->cart->get_cart();
		$parts = array();
                foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['_wjecf_free_product_coupon'] ) ) {
				$pr_id = $this->productHelper->get_product_or_variation_id( $cart_item['data'] );
				$qty = $cart_item['quantity'];			
				$parts[] = $qty == 1 ? strval( $pr_id ) : $qty . 'x' . $pr_id;
                                
			}
		}
		if ( empty( $parts ) ) {
			return false;
		} else {			
			return add_query_arg( 'fill_cart', implode( ',', $parts ), $base_url == false ? wc_get_cart_url() : $base_url );
		}

	}

  public function DeleteActiveCarts($userId) {
      global $wpdb;

      $timestamp = time() - (60 * $this->timeAbandoned);
      $table_name = $wpdb->prefix . 'swi_abandoned_carts';
      $data = $wpdb->delete($table_name, [ 'user_id' => $userId, 'timestamp' => ">= '{$timestamp}'" ]);

      return $data;
  }

  public function DeleteAbandonedCartData($cart_secret) {
    global $wpdb;

    if(!preg_match('/^[a-zA-Z\d]+$/', $cart_secret) || !$cart_secret) {
      return false;
    }

    $table_name = $wpdb->prefix . 'swi_abandoned_carts';
    $data = $wpdb->delete($table_name, [ 'cart_secret' => sanitize_text_field($cart_secret) ]);

    return $data;

  }

  public function GetAbandonedCartsData($user_id) {
    global $wpdb;

    $user_id = (int) $user_id;

    if(!$user_id) {
      return false;
    }

    $table_name = $wpdb->prefix . 'swi_abandoned_carts';
    $sql = "SELECT * FROM $table_name WHERE user_id = '{$user_id}' ORDER BY id ASC";
    $data = $wpdb->get_results($sql);
    $count = count($data);

    if($count > 0) {
      $data = $data[$count-1];
      return $data;
    }

    return false;

  }

  public function GetAbandonedCartData($cart_secret) {
    global $wpdb;

    $cart_secret = sanitize_text_field($cart_secret);

    if(!preg_match('/^[a-zA-Z\d]+$/', $cart_secret) || !$cart_secret) {
      return false;
    }

    $table_name = $wpdb->prefix . 'swi_abandoned_carts';
    $sql = "SELECT * FROM $table_name WHERE cart_secret = '{$cart_secret}' ORDER BY id ASC";
    $data = $wpdb->get_results($sql);
    $count = count($data);

    if($count > 0) {
      $data = $data[$count-1];
      return $data;
    }

    return false;

  }

  public function UpdateAbandonedCartData($userID, $cart_meta) {
    global $wpdb;
    if( !is_numeric($userID) || !$cart_meta || count($cart_meta) == 0 || !$userID || !is_user_logged_in() ) {
      return false;
    }

    $userID = absint($userID);
    $timestamp = time();
    $abandoned_time_window = $timestamp - (60 * $this->timeAbandoned);

    $table_name = $wpdb->prefix . 'swi_abandoned_carts';
    $sql = "SELECT id, cart_secret FROM $table_name WHERE user_id = '{$userID}' AND timestamp > '{$abandoned_time_window}' ORDER BY id ASC";
    $data = $wpdb->get_results($sql);
    $count = count($data);

    if($count > 0) {
      $data = $data[$count-1];

      if(count($cart_meta[0]['cart']) == 0) { // empty cart
        $this->DeleteAbandonedCartData($data->cart_secret);
        return false;
      }

      $wpdb->update( $table_name, [ 
        'timestamp' => $timestamp, 
        'cart_meta' => serialize($cart_meta) ], 
        [ 'id' => $data->id ] );

      return $data->cart_secret;
    }

    if(count($cart_meta[0]['cart']) == 0) { // empty cart
      return false;
    }

    $secret = $this->tokens->getToken();

    $data = $wpdb->insert($table_name, [
      'user_id' => $userID,
      'cart_secret' => $secret,
      'cart_meta' => serialize($cart_meta),
      'timestamp' => $timestamp
    ]);

    return $secret;
  }


  function add_referral_meta( $order_id, $posted = null) {
    if(isset($_COOKIE['swi_abandoned'])) {
      $ref_url = sanitize_text_field($_COOKIE['swi_abandoned']);
    } else { 
      $ref_url = null;
    }

    $cartData = $this->GetAbandonedCartsData(get_current_user_id());

    if($cartData->timestamp + $this->timeAbandoned > time()) {
      $this->DeleteAbandonedCartData($cartData->cart_secret);
    }

    update_post_meta( $order_id, 'swi_abandoned', $ref_url );

    setcookie( 'swi_abandoned', true, time()-100, '/', $_SERVER['HTTP_HOST'], false);
  }
        
        /**
	 * Handle the fill_cart querystring
	 */
  public function check_url() {
    global $woocommerce;

    if ( ! isset($_GET['fill_cart'])) {
      return;
    }

    $original_notices = wc_get_notices();
    wc_clear_notices();

    $data = $this->GetAbandonedCartData($_GET['fill_cart']);
    $my_notices = [];

    if(!$data) {
      $my_notices[] = sprintf( __('Unknown cart &quot;%d&quot;.', 'soft79_wccl' ), $_GET['fill_cart'] );
    } else {
      $woocommerce->session->cart = unserialize($data->cart_meta)[0]['cart'];

      if(!isset($_COOKIE['swi_abandoned'])) {

        setcookie( 'swi_abandoned', $data->cart_secret, time()+3600*24, '/', $_SERVER['HTTP_HOST'], false);
      }
    }

    $catched_notices = wc_get_notices();

    //Restore original notices
    WC()->session->set( 'wc_notices', $original_notices );

    //Collect notices that were added in the meantime (preferably none)
    foreach ( $catched_notices as $notice_type => $messages ) {
      if ($notice_type != 'success') {
        foreach ( $messages as $message ) {
          $my_notices[] = $message . "<br>";
        }
      }
    }

    if ( count( $my_notices ) == 1 ) {
      wc_add_notice( $my_notices[0], 'error' );
    } elseif ( count( $my_notices ) > 1 ) {
      wc_add_notice( __( 'Something went wrong populating your cart:', 'soft79_wccl' ) . "<br><ul>\n<li>" . implode( "\n<li>", $my_notices ) . "\n</ul>", 'error' );
    }

    //Refer to url without ?fill_cart to prevent fill_cart to be executed after customer performs a cart update (because WooCommerce also redirects to referer )
    $requested_url  = is_ssl() ? 'https://' : 'http://';
    $requested_url .= $_SERVER['HTTP_HOST'];           
    $requested_url .= $_SERVER['REQUEST_URI'];
    wp_safe_redirect( remove_query_arg( 'fill_cart', ( $requested_url ) ) );
    exit;

  }

	/**
	 * Add the product to the cart. Takes care of variations
	 *
	 * @param $product WC_Product The product or variation to append
	 * @param $quantity int The quantity of the products to append
	 */
	protected function add_to_cart( $product, $quantity ) {
		if ( $this->productHelper->is_variation( $product ) ) {
			$variation = $this->productHelper->get_product_variation_attributes( $product );
			$variation_id = $this->productHelper->get_variation_id( $product );
			$parent_id =  $this->productHelper->get_variable_product_id( $product );
			WC()->cart->add_to_cart( $parent_id, $quantity, $variation_id, $variation );
		} else {
			$product_id = $this->productHelper->get_product_id( $product );
			WC()->cart->add_to_cart( $product_id, $quantity );
		}
	}


	/**
	 * Checks whether item is in the cart and returns the key.
	 * 
	 * @returns string|bool false if not found, otherwise the cart_item_key of the product
	 */
	protected function get_cart_item_key( $cart_contents, $product_or_variation_id ) {
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['_wjecf_free_product_coupon'] ) ) {
				$pr_id = $this->productHelper->get_product_or_variation_id( $cart_item['data'] );
				if ( $pr_id == $product_or_variation_id ) {
					return $cart_item_key;
				}				
			}
		}
		return false;
	}


	/**
	 * Checks if stock is sufficient for the given product.
	 *
	 * @returns int|bool Value of $quantity if stock is sufficient or not managed, otherwise the available quantity. Returns false if product does not exist
	 */
	protected function get_stock_available( $product, $quantity ) {
		if ( $product == false ) {
			return false;
		} elseif ( $product->managing_stock() && ! $product->backorders_allowed() ) {
        	$available = min( $quantity, intval( $product->get_stock_quantity() ) );
        } else {
        	$available = $quantity;
        }
        return $available;
	}
	
	/**
	 * Get the plugin path without trailing slash.
	 * @return string
	 */    
	public function pluginDirectory() {
		return untrailingslashit( dirname( $this->pluginFullPath() ) );
	} 

	/**
	 * Plugin filename including path
	 * @return string
	 */
	public function pluginFullPath() {
		return __FILE__;
	} 

	/**
	 * Plugin base name ( path relative to wp-content/plugins/ )
	 * @return string
	 */
	public function pluginBase() {
		return plugin_basename( $this->pluginFullPath() );
	} 	

	public function pluginVersion() {
		return $this->version;
	}  	
}
