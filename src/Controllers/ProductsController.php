<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductsController extends WC_REST_Products_V1_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'smart_products';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'product';

	/**
	 * Initialize product actions.
	 */
	public function __construct() {
		add_filter( "woocommerce_rest_{$this->post_type}_query", array( $this, 'query_args' ), 10, 2 );
		add_action( "woocommerce_rest_insert_{$this->post_type}", array( $this, 'clear_transients' ) );
	}

	/**
	 * Register the routes for products.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

  /**
   * Get a collection of posts.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_items( $request ) {
    $args                         = array();
    $args['offset']               = $request['offset'];
    $args['order']                = $request['order'];
    $args['orderby']              = $request['orderby'];
    $args['paged']                = $request['page'];
    $args['post__in']             = $request['include'];
    $args['post__not_in']         = $request['exclude'];
    $args['posts_per_page']       = $request['per_page'];
    $args['name']                 = $request['slug'];
    $args['post_parent__in']      = $request['parent'];
    $args['post_parent__not_in']  = $request['parent_exclude'];
    $args['s']                    = $request['search'];

    $args['date_query'] = array();
    // Set before into date query. Date query must be specified as an array of an array.
    if ( isset( $request['before'] ) ) {
      $args['date_query'][0]['before'] = $request['before'];
    }

    // Set after into date query. Date query must be specified as an array of an array.
    if ( isset( $request['after'] ) ) {
      $args['date_query'][0]['after'] = $request['after'];
    }

    // Updated at filter
    $update_args = array();

    if ( isset( $request['updated_at_min'] ) ) {
      $update_args[0]['after'] = $request['updated_at_min'];
    }

    if ( isset( $request['updated_at_max'] ) ) {
      $update_args[0]['before'] = $request['updated_at_max'];
    }

    if ( count($update_args) > 0 ) {
      $update_args[0]['column'] = 'post_modified_gmt';
      array_push($args['date_query'], $update_args);
    }

    if ( 'wc/v1' === $this->namespace ) {
      if ( is_array( $request['filter'] ) ) {
        $args = array_merge( $args, $request['filter'] );
        unset( $args['filter'] );
      }
    }

    // Force the post_type argument, since it's not a user input variable.
    $args['post_type'] = $this->post_type;

    /**
     * Filter the query arguments for a request.
     *
     * Enables adding extra arguments or setting defaults for a post
     * collection request.
     *
     * @param array           $args    Key value array of query var to query value.
     * @param WP_REST_Request $request The request used.
     */
    $args = apply_filters( "woocommerce_rest_{$this->post_type}_query", $args, $request );
    $query_args = $this->prepare_items_query( $args, $request );

    $posts_query = new WP_Query();
    $query_result = $posts_query->query( $query_args );

    $posts = array();
    foreach ( $query_result as $post ) {
      if ( ! wc_rest_check_post_permissions( $this->post_type, 'read', $post->ID ) ) {
        continue;
      }

      $data = $this->prepare_item_for_response( $post, $request );
      $posts[] = $this->prepare_response_for_collection( $data );
    }

    $page = (int) $query_args['paged'];
    $total_posts = $posts_query->found_posts;

    if ( $total_posts < 1 ) {
      // Out-of-bounds, run the query again without LIMIT for total count
      unset( $query_args['paged'] );
      $count_query = new WP_Query();
      $count_query->query( $query_args );
      $total_posts = $count_query->found_posts;
    }

    $max_pages = ceil( $total_posts / (int) $query_args['posts_per_page'] );

    $response = rest_ensure_response( $posts );
    $response->header( 'X-WP-Total', (int) $total_posts );
    $response->header( 'X-WP-TotalPages', (int) $max_pages );

    $request_params = $request->get_query_params();
    if ( ! empty( $request_params['filter'] ) ) {
      // Normalize the pagination params.
      unset( $request_params['filter']['posts_per_page'] );
      unset( $request_params['filter']['paged'] );
    }
    $base = add_query_arg( $request_params, rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

    if ( $page > 1 ) {
      $prev_page = $page - 1;
      if ( $prev_page > $max_pages ) {
        $prev_page = $max_pages;
      }
      $prev_link = add_query_arg( 'page', $prev_page, $base );
      $response->link_header( 'prev', $prev_link );
    }
    if ( $max_pages > $page ) {
      $next_page = $page + 1;
      $next_link = add_query_arg( 'page', $next_page, $base );
      $response->link_header( 'next', $next_link );
    }

    return $response;
  }

	/**
	 * Get the query params for collections of attachments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['slug'] = array(
			'description'       => __( 'Limit result set to products with a specific slug.', 'woocommerce' ),
			'type'              => 'string',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['status'] = array(
			'default'           => 'any',
			'description'       => __( 'Limit result set to products assigned a specific status.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => array_merge( array( 'any' ), array_keys( get_post_statuses() ) ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['type'] = array(
			'description'       => __( 'Limit result set to products assigned a specific type.', 'woocommerce' ),
			'type'              => 'string',
			'enum'              => array_keys( wc_get_product_types() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['category'] = array(
			'description'       => __( 'Limit result set to products assigned a specific category ID.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['tag'] = array(
			'description'       => __( 'Limit result set to products assigned a specific tag ID.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['shipping_class'] = array(
			'description'       => __( 'Limit result set to products assigned a specific shipping class ID.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['attribute'] = array(
			'description'       => __( 'Limit result set to products with a specific attribute.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['attribute_term'] = array(
			'description'       => __( 'Limit result set to products with a specific attribute term ID (required an assigned attribute).', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['sku'] = array(
			'description'       => __( 'Limit result set to products with a specific SKU.', 'woocommerce' ),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
