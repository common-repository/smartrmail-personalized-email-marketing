<?php

namespace SmartrMail\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductHelper {

    public function get_product_id( $product ) {
        //Since WC 3.0
        if ( is_callable( array( $product, 'get_id' ) ) ) {
            return $product->get_id();
        }
        return $product->id;
    }

    public function is_variation( $product ) {
        return is_a($product, 'WC_Product_Variation');
    }

    public function get_variable_product_id( $product ) {
        if ( ! $this->is_variation( $product ) ) {
            return false;
        }

        if ( is_callable( array( $product, 'get_parent_id' ) ) ) {
            return $product->get_parent_id();
        } else {
            return wp_get_post_parent_id( $product->variation_id );
        }
    }    

    /**
     * Get current variation id
     * @return int|bool False if this is not a variation
     */
    public function get_variation_id( $product ) {
        if ( ! $this->is_variation( $product ) ) {
            return false;
        }

        if ( is_callable( array( $product, 'get_id' ) ) ) {
            return $product->get_id(); 
        } elseif ( is_callable( array( $product, 'get_variation_id' ) ) ) {
            return $product->get_variation_id(); 
        }
        return $product->variation_id;
    }

    /**
     * Retrieve the id of the product or the variation id if it's a variant.
     * 
     * @param WC_Product $product 
     * @return int|bool The variation or product id. False if not a valid product
     */
    public function get_product_or_variation_id( $product ) {
        if ( $this->is_variation( $product ) ) {
            return $this->get_variation_id( $product );
        } elseif ( is_a($product, 'WC_Product') ) {
            return $this->get_product_id( $product );
        } else {
            return false;
        }
    }

	/**
	 * Get attibutes/data for an individual variation from the database and maintain it's integrity.
	 * @since  1.1.0
	 * @param  int $variation_id
	 * @return array
	 */
    public function get_product_variation_attributes( $product ) {
    	//Since WC 2.4.0
        if ( is_callable( 'wc_get_product_variation_attributes' ) ) {
            return wc_get_product_variation_attributes( $product->get_id() ); 
        }
        return $product->variation_data;
    }      
}