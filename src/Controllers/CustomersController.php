<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class CustomersController extends WC_REST_Customers_V1_Controller {
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
  protected $rest_base = 'smart_customers';
  /**
   * Register the routes for customers.
   */
  public function register_routes() {
    register_rest_route( $this->namespace, '/' . $this->rest_base, array(
      array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array( $this, 'get_items' ),
        'permission_callback' => array( $this, 'get_items_permissions_check' ),
        'args'                => $this->get_collection_params(),
      ),
      'schema' => array( $this, 'get_public_item_schema' )
    ) );
  }
  /**
   * Get all customers.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function get_items( $request ) {
    $prepared_args = array();
    $prepared_args['exclude'] = $request['exclude'];
    $prepared_args['include'] = $request['include'];
    $prepared_args['order']   = $request['order'];
    $prepared_args['number']  = $request['per_page'];
    if ( ! empty( $request['offset'] ) ) {
      $prepared_args['offset'] = $request['offset'];
    } else {
      $prepared_args['offset'] = ( $request['page'] - 1 ) * $prepared_args['number'];
    }
    $orderby_possibles = array(
      'id'              => 'ID',
      'include'         => 'include',
      'name'            => 'display_name',
      'registered_date' => 'registered',
    );
    $prepared_args['orderby'] = $orderby_possibles[ $request['orderby'] ];
    $prepared_args['search']  = $request['search'];
    if ( '' !== $prepared_args['search'] ) {
      $prepared_args['search'] = '*' . $prepared_args['search'] . '*';
    }
    // Filter by email.
    if ( ! empty( $request['email'] ) ) {
      $prepared_args['search']         = $request['email'];
      $prepared_args['search_columns'] = array( 'user_email' );
    }
    // Filter by role.
    if ( 'all' !== $request['role'] ) {
      $prepared_args['role'] = $request['role'];
    }
    // Filter by date

    $prepared_args['date_query'] = array();
    // Set before into date query. Date query must be specified as an array of an array.
    if ( isset( $request['before'] ) ) {
      $prepared_args['date_query'][0]['before'] = $request['before'];
    }
    // Set after into date query. Date query must be specified as an array of an array.
    if ( isset( $request['after'] ) ) {
      $prepared_args['date_query'][0]['after'] = $request['after'];
    }

    // update min and max filter 
    $meta_query_args = array();
    $updated_at_args = array();

    if ( isset( $request['updated_at_min'] ) ) {
      array_push($updated_at_args, array(
        array(
          'key'     => 'last_update',
          'value'   => $request['updated_at_min'],
          'compare' => '>='
        )
      ));
    }

    if ( isset( $request['updated_at_max'] ) ) {
      array_push($updated_at_args, array(
        array(
          'key'     => 'last_update',
          'value'   => $request['updated_at_max'],
          'compare' => '<='
        )
      ));
    }

    if ( count($updated_at_args) > 0 ) {
      array_push($meta_query_args, $updated_at_args);
      $prepared_args['meta_query'] = $meta_query_args;
    }

    /**
     * Filter arguments, before passing to WP_User_Query, when querying users via the REST API.
     *
     * @see https://developer.wordpress.org/reference/classes/wp_user_query/
     *
     * @param array           $prepared_args Array of arguments for WP_User_Query.
     * @param WP_REST_Request $request       The current request.
     */
    $prepared_args = apply_filters( 'woocommerce_rest_customer_query', $prepared_args, $request );
    $query = new WP_User_Query( $prepared_args );
    $users = array();
    foreach ( $query->results as $user ) {
      $data = $this->prepare_item_for_response( $user, $request );
      $users[] = $this->prepare_response_for_collection( $data );
    }
    $response = rest_ensure_response( $users );
    // Store pagination values for headers then unset for count query.
    $per_page = (int) $prepared_args['number'];
    $page = ceil( ( ( (int) $prepared_args['offset'] ) / $per_page ) + 1 );
    $prepared_args['fields'] = 'ID';
    $total_users = $query->get_total();
    if ( $total_users < 1 ) {
      // Out-of-bounds, run the query again without LIMIT for total count.
      unset( $prepared_args['number'] );
      unset( $prepared_args['offset'] );
      $count_query = new WP_User_Query( $prepared_args );
      $total_users = $count_query->get_total();
    }
    $response->header( 'X-WP-Total', (int) $total_users );
    $max_pages = ceil( $total_users / $per_page );
    $response->header( 'X-WP-TotalPages', (int) $max_pages );
    $base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );
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
   * Get the query params for collections.
   *
   * @return array
   */
  public function get_collection_params() {
    $params = parent::get_collection_params();
    $params['context']['default'] = 'view';
    $params['exclude'] = array(
      'description'       => __( 'Ensure result set excludes specific IDs.', 'woocommerce' ),
      'type'              => 'array',
      'items'             => array(
        'type'          => 'integer',
      ),
      'default'           => array(),
      'sanitize_callback' => 'wp_parse_id_list',
    );
    $params['include'] = array(
      'description'       => __( 'Limit result set to specific IDs.', 'woocommerce' ),
      'type'              => 'array',
      'items'             => array(
        'type'          => 'integer',
      ),
      'default'           => array(),
      'sanitize_callback' => 'wp_parse_id_list',
    );
    $params['offset'] = array(
      'description'        => __( 'Offset the result set by a specific number of items.', 'woocommerce' ),
      'type'               => 'integer',
      'sanitize_callback'  => 'absint',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['order'] = array(
      'default'            => 'asc',
      'description'        => __( 'Order sort attribute ascending or descending.', 'woocommerce' ),
      'enum'               => array( 'asc', 'desc' ),
      'sanitize_callback'  => 'sanitize_key',
      'type'               => 'string',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['orderby'] = array(
      'default'            => 'name',
      'description'        => __( 'Sort collection by object attribute.', 'woocommerce' ),
      'enum'               => array(
        'id',
        'include',
        'name',
        'registered_date',
      ),
      'sanitize_callback'  => 'sanitize_key',
      'type'               => 'string',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['email'] = array(
      'description'        => __( 'Limit result set to resources with a specific email.', 'woocommerce' ),
      'type'               => 'string',
      'format'             => 'email',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['role'] = array(
      'description'        => __( 'Limit result set to resources with a specific role.', 'woocommerce' ),
      'type'               => 'string',
      'default'            => 'customer',
      'enum'               => array_merge( array( 'all' ), $this->get_role_names() ),
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['after'] = array(
      'description'        => __( 'Limit response to resources published after a given ISO8601 compliant date.', 'woocommerce' ),
      'type'               => 'string',
      'format'             => 'date-time',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['before'] = array(
      'description'        => __( 'Limit response to resources published before a given ISO8601 compliant date.', 'woocommerce' ),
      'type'               => 'string',
      'format'             => 'date-time',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['updated_at_min'] = array(
      'description'        => __( 'Limit response to resources updated after a given integer value as date', 'woocommerce' ),
      'type'               => 'string',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['updated_at_max'] = array(
      'description'        => __( 'Limit response to resources updated before a given integer value as date', 'woocommerce' ),
      'type'               => 'string',
      'validate_callback'  => 'rest_validate_request_arg',
    );
    return $params;
  }
}
