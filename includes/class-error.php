<?php
/**
 * Microsub Error Class.
 *
 * Handles error responses following the Micropub error format.
 *
 * @package Microsub
 */

namespace Microsub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Error
 *
 * Error handling for Microsub API responses.
 */
class Error {

	/**
	 * Error codes and their HTTP status codes.
	 *
	 * @var array
	 */
	const ERROR_CODES = array(
		'invalid_request'    => 400,
		'unauthorized'       => 401,
		'forbidden'          => 403,
		'insufficient_scope' => 403,
		'not_found'          => 404,
		'not_implemented'    => 501,
		'server_error'       => 500,
	);

	/**
	 * Create an error response.
	 *
	 * @param string $error             The error code.
	 * @param string $error_description Human-readable error description.
	 * @return \WP_REST_Response The error response.
	 */
	public static function response( $error, $error_description = '' ) {
		$status = isset( self::ERROR_CODES[ $error ] ) ? self::ERROR_CODES[ $error ] : 400;

		$data = array(
			'error' => $error,
		);

		if ( ! empty( $error_description ) ) {
			$data['error_description'] = $error_description;
		}

		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Invalid request error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function invalid_request( $description = '' ) {
		return self::response(
			'invalid_request',
			$description ?: \__( 'The request is missing a required parameter or is otherwise invalid.', 'microsub' )
		);
	}

	/**
	 * Unauthorized error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function unauthorized( $description = '' ) {
		return self::response(
			'unauthorized',
			$description ?: \__( 'The request lacks valid authentication credentials.', 'microsub' )
		);
	}

	/**
	 * Forbidden error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function forbidden( $description = '' ) {
		return self::response(
			'forbidden',
			$description ?: \__( 'The authenticated user does not have permission to perform this action.', 'microsub' )
		);
	}

	/**
	 * Insufficient scope error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function insufficient_scope( $description = '' ) {
		return self::response(
			'insufficient_scope',
			$description ?: \__( 'The access token does not have the required scope.', 'microsub' )
		);
	}

	/**
	 * Not found error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function not_found( $description = '' ) {
		return self::response(
			'not_found',
			$description ?: \__( 'The requested resource was not found.', 'microsub' )
		);
	}

	/**
	 * Not implemented error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function not_implemented( $description = '' ) {
		return self::response(
			'not_implemented',
			$description ?: \__( 'This action is not implemented by the server.', 'microsub' )
		);
	}

	/**
	 * Server error.
	 *
	 * @param string $description Optional description.
	 * @return \WP_REST_Response
	 */
	public static function server_error( $description = '' ) {
		return self::response(
			'server_error',
			$description ?: \__( 'An internal server error occurred.', 'microsub' )
		);
	}
}
