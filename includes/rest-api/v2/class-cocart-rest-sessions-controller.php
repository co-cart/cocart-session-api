<?php
/**
 * REST API: CoCart_REST_Sessions_V2_Controller class
 *
 * @author  Sébastien Dumont
 * @package CoCart\RESTAPI\Sessions\v2
 * @since   3.0.0 Introduced.
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a list of carts in session.
 *
 * This REST API controller handles the request to get sessions
 * via "cocart/v2/sessions" endpoint.
 *
 * @package CoCart REST API/API
 */
class CoCart_REST_Sessions_v2_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @access protected
	 *
	 * @var string
	 */
	protected $namespace = 'cocart/v2';

	/**
	 * Route base.
	 *
	 * @access protected
	 *
	 * @var string
	 */
	protected $rest_base = 'sessions';

	/**
	 * Register the routes for index.
	 *
	 * @access public
	 *
	 * @since 3.0.0 Introduced
	 * @since 3.1.0 Added schema information.
	 */
	public function register_routes() {
		// Get Sessions - cocart/v2/sessions (GET).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_carts_in_session' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_object_schema' ),
			),
		);
	} // register_routes()

	/**
	 * Check whether a given request has permission to read site data.
	 *
	 * @throws CoCart\DataException Exception if invalid data is detected.
	 *
	 * @access public
	 *
	 * @since 3.0.0 Introduced
	 * @since 4.0.0 Use namespace for DataException.
	 * @since 4.0.0 Added check for the access token maybe required.
	 *
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check() {
		try {
			$access_token         = cocart_get_setting( 'general', 'access_token' );
			$require_access_token = cocart_get_setting( 'general', 'require_access_token' );

			if ( $require_access_token === 'yes' && ! empty( $access_token ) ) {
				$requested_token = $request->get_header( 'x-cocart-access-token' );

				// Validate requested token.
				if ( ! empty( $requested_token ) && ! wp_is_uuid( $requested_token ) ) {
					throw new \CoCart\DataException( 'cocart_rest_invalid_token', __( 'Invalid token provided.', 'cart-rest-api-for-woocommerce' ), rest_authorization_required_code() );
				}

				// If token matches then proceed.
				if ( $access_token == $requested_token ) {
					return true;
				} else {
					throw new \CoCart\DataException( 'cocart_rest_permission_denied', __( 'Permission Denied.', 'cart-rest-api-for-woocommerce' ), rest_authorization_required_code() );
				}
			}

			if ( ! wc_rest_check_manager_permissions( 'settings', 'read' ) ) {
				throw new \CoCart\DataException( 'cocart_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'cart-rest-api-for-woocommerce' ), rest_authorization_required_code() );
			}
		} catch ( \CoCart\DataException $e ) {
			return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
		}

		return true;
	} // END get_items_permissions_check()

	/**
	 * Returns carts in session if any exists.
	 *
	 * @throws CoCart\DataException Exception if invalid data is detected.
	 *
	 * @access public
	 *
	 * @since 3.0.0 Introduced.
	 * @since 4.0.0 Added pagination and order filtering.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response Returns the carts in session from the database.
	 */
	public function get_carts_in_session( $request = array() ) {
		try {
			global $wpdb;

			$params = $request->get_params();

			$page     = ! empty( $params['page'] ) ? (int) $params['page'] : '1'; // Offset
			$per_page = ! empty( $params['per_page'] ) ? (int) $params['per_page'] : '10'; // Limit
			$order    = ! empty( $params['order'] ) ? strtoupper( $params['order'] ) : 'DESC';
			$order_by = ! empty( $params['orderby'] ) ? strtolower( $params['orderby'] ) : 'cart_created';

			// Gets the max results.
			$max_results    = $wpdb->get_results(
				"
				SELECT COUNT(*) as num_rows 
				FROM {$wpdb->prefix}cocart_carts",
				ARRAY_A
			);
			$total_sessions = $max_results[0]['num_rows'];

			$results = $wpdb->get_results(
				"
				SELECT * 
				FROM {$wpdb->prefix}cocart_carts
				ORDER BY {$order_by} {$order}
				LIMIT {$page}, {$per_page}",
				ARRAY_A
			);

			if ( empty( $results ) ) {
				throw new \CoCart\DataException( 'cocart_no_carts_in_session', __( 'No carts in session!', 'cart-rest-api-for-woocommerce' ), 404 );
			}

			// Contains the results of sessions.
			$sessions = array();

			foreach ( $results as $key => $cart ) {
				$cart_value = maybe_unserialize( $cart['cart_value'] );
				$customer   = maybe_unserialize( $cart_value['customer'] );

				$email      = ! empty( $customer['email'] ) ? $customer['email'] : '';
				$first_name = ! empty( $customer['first_name'] ) ? $customer['first_name'] : '';
				$last_name  = ! empty( $customer['last_name'] ) ? ' ' . $customer['last_name'] : '';

				if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
					$name = $first_name . $last_name;
				} else {
					$name = '';
				}

				$sessions[] = array(
					'cart_id'         => $cart['cart_id'],
					'cart_key'        => $cart['cart_key'],
					'customers_name'  => $name,
					'customers_email' => $email,
					'cart_created'    => gmdate( 'm/d/Y H:i:s', $cart['cart_created'] ),
					'cart_expiry'     => gmdate( 'm/d/Y H:i:s', $cart['cart_expiry'] ),
					'cart_source'     => $cart['cart_source'],
					'link'            => rest_url( sprintf( '/%s/%s', $this->namespace, 'session/' . $cart['cart_key'] ) ),
				);
			}

			$results = array(
				'sessions'       => $sessions,
				'page'           => (int) $page,
				'total_pages'    => $per_page > 1 ? (int) ceil( $total_sessions / (int) $per_page ) : 1,
				'total_sessions' => (int) $total_sessions,
			);

			$base = add_query_arg( $request->get_query_params(), rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

			if ( $page > 1 ) {
				$prev_page = $page - 1;

				if ( $prev_page > $total_sessions ) {
					$prev_page = $total_sessions;
				}

				$results['prev'] = add_query_arg( 'page', $prev_page, $base );
			}

			if ( $total_sessions > $page ) {
				$next_page       = $page + 1;
				$results['next'] = add_query_arg( 'page', $next_page, $base );
			}

			return CoCart_Response::get_response( $results, $this->namespace, $this->rest_base );
		} catch ( \CoCart\DataException $e ) {
			return CoCart_Response::get_error_response( $e->getErrorCode(), $e->getMessage(), $e->getCode(), $e->getAdditionalData() );
		}
	} // END get_carts_in_session()

	/**
	 * Get the schema for returning the sessions.
	 *
	 * @access public
	 *
	 * @since 3.1.0 Introduced
	 *
	 * @return array
	 */
	public function get_public_object_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'cocart_sessions',
			'type'       => 'object',
			'properties' => array(
				'cart_id'         => array(
					'description' => __( 'Unique identifier for the session.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'cart_key'        => array(
					'description' => __( 'Unique identifier for the customers cart.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'customers_name'  => array(
					'description' => __( 'The name of the customer provided.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'customers_email' => array(
					'description' => __( 'The email the customer provided.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'cart_created'    => array(
					'description' => __( 'The date and time the cart was created, in the site\'s timezone.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'cart_expiry'     => array(
					'description' => __( 'The date and time the cart will expire, in the site\'s timezone.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'cart_source'     => array(
					'description' => __( 'Identifies the source of how the cart was made, native or headless.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'link'            => array(
					'description' => __( 'URL to the individual cart in session.', 'cart-rest-api-for-woocommerce' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);
	} // END get_public_object_schema()

} // END class
