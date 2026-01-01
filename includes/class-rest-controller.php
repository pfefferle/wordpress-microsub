<?php
/**
 * Microsub REST Controller.
 *
 * @package Microsub
 */

namespace Microsub;

/**
 * Class Rest_Controller
 *
 * Microsub REST API implementation.
 */
class Rest_Controller extends \WP_REST_Controller {

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = 'microsub/1.0';

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'endpoint';

	/**
	 * Scope requirements for each action.
	 *
	 * @var array
	 */
	const SCOPES = array(
		'timeline' => 'read',
		'channels' => 'channels',
		'follow'   => 'follow',
		'unfollow' => 'follow',
		'mute'     => 'mute',
		'unmute'   => 'mute',
		'block'    => 'block',
		'unblock'  => 'block',
		'search'   => 'read',
		'preview'  => 'read',
	);

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_permission( $request );
	}

	/**
	 * Check if a given request has access to create items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_permission( $request );
	}

	/**
	 * Check if the user has permission to access the endpoint.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	protected function check_permission( $request ) {
		$action = $request->get_param( 'action' );

		if ( empty( $action ) ) {
			return true;
		}

		$user_id = \get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_Error(
				'unauthorized',
				\__( 'Authentication required.', 'microsub' ),
				array( 'status' => 401 )
			);
		}

		$required_scope = isset( self::SCOPES[ $action ] ) ? self::SCOPES[ $action ] : 'read';

		if ( ! $this->has_scope( $required_scope ) ) {
			return new \WP_Error(
				'insufficient_scope',
				\sprintf(
					/* translators: %s: Required scope */
					\__( 'This action requires the "%s" scope.', 'microsub' ),
					$required_scope
				),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check if the current user has the required scope.
	 *
	 * @param string $required_scope The required scope.
	 * @return bool True if the user has the scope.
	 */
	protected function has_scope( $required_scope ) {
		/**
		 * Filters the current token scopes.
		 *
		 * IndieAuth plugin should hook into this to provide scopes.
		 *
		 * @param array $scopes Default empty array.
		 */
		$scopes = \apply_filters( 'indieauth_scopes', array() );

		// If IndieAuth doesn't provide scopes, check if user is logged in.
		if ( empty( $scopes ) && \is_user_logged_in() ) {
			// Grant all scopes to logged-in users without IndieAuth.
			$scopes = array( 'read', 'follow', 'mute', 'block', 'channels', 'create', 'update', 'delete' );
		}

		// 'read' is implied by any scope.
		if ( 'read' === $required_scope && ! empty( $scopes ) ) {
			return true;
		}

		return \in_array( $required_scope, $scopes, true );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$action = $request->get_param( 'action' );

		if ( empty( $action ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: action', 'microsub' ) );
		}

		$user_id = \get_current_user_id();

		switch ( $action ) {
			case 'channels':
				return $this->get_channels( $user_id );

			case 'timeline':
				return $this->get_timeline( $request, $user_id );

			case 'follow':
				return $this->get_following( $request, $user_id );

			case 'mute':
				return $this->get_muted( $request, $user_id );

			case 'block':
				return $this->get_blocked( $request, $user_id );

			case 'search':
				return $this->search( $request, $user_id );

			case 'preview':
				return $this->preview( $request, $user_id );

			default:
				return Error::invalid_request(
					\sprintf(
						/* translators: %s: Action name */
						\__( 'Unknown action: %s', 'microsub' ),
						$action
					)
				);
		}
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$action = $request->get_param( 'action' );

		if ( empty( $action ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: action', 'microsub' ) );
		}

		$user_id = \get_current_user_id();

		switch ( $action ) {
			case 'channels':
				return $this->handle_channels_post( $request, $user_id );

			case 'timeline':
				return $this->handle_timeline_post( $request, $user_id );

			case 'follow':
				return $this->follow( $request, $user_id );

			case 'unfollow':
				return $this->unfollow( $request, $user_id );

			case 'mute':
				return $this->mute( $request, $user_id );

			case 'unmute':
				return $this->unmute( $request, $user_id );

			case 'block':
				return $this->block( $request, $user_id );

			case 'unblock':
				return $this->unblock( $request, $user_id );

			default:
				return Error::invalid_request(
					\sprintf(
						/* translators: %s: Action name */
						\__( 'Unknown action: %s', 'microsub' ),
						$action
					)
				);
		}
	}

	/**
	 * Get list of channels.
	 *
	 * Aggregates channels from all registered adapters.
	 *
	 * @param int $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function get_channels( $user_id ) {
		// Start with empty array to aggregate from multiple adapters.
		$channels = \apply_filters( 'microsub_get_channels', array(), $user_id );

		if ( empty( $channels ) ) {
			return Error::not_implemented( \__( 'No adapter provides channel support.', 'microsub' ) );
		}

		// Deduplicate channels by uid.
		$unique = array();
		foreach ( $channels as $channel ) {
			if ( isset( $channel['uid'] ) && ! isset( $unique[ $channel['uid'] ] ) ) {
				$unique[ $channel['uid'] ] = $channel;
			}
		}

		return new \WP_REST_Response( array( 'channels' => \array_values( $unique ) ), 200 );
	}

	/**
	 * Handle POST requests for channels.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function handle_channels_post( $request, $user_id ) {
		$method = $request->get_param( 'method' );

		if ( empty( $method ) || 'create' === $method ) {
			$name = $request->get_param( 'name' );

			if ( empty( $name ) ) {
				return Error::invalid_request( \__( 'Missing required parameter: name', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_create_channel', null, $name, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides channel creation.', 'microsub' ) );
			}

			return new \WP_REST_Response( $result, 200 );
		}

		if ( 'update' === $method ) {
			$uid  = $request->get_param( 'channel' );
			$name = $request->get_param( 'name' );

			if ( empty( $uid ) || empty( $name ) ) {
				return Error::invalid_request( \__( 'Missing required parameters: channel, name', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_update_channel', null, $uid, $name, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides channel updating.', 'microsub' ) );
			}

			return new \WP_REST_Response( $result, 200 );
		}

		if ( 'delete' === $method ) {
			$uid = $request->get_param( 'channel' );

			if ( empty( $uid ) ) {
				return Error::invalid_request( \__( 'Missing required parameter: channel', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_delete_channel', null, $uid, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides channel deletion.', 'microsub' ) );
			}

			if ( false === $result ) {
				return Error::server_error( \__( 'Failed to delete channel.', 'microsub' ) );
			}

			return new \WP_REST_Response( null, 200 );
		}

		if ( 'order' === $method ) {
			$channels = $request->get_param( 'channels' );

			if ( empty( $channels ) || ! \is_array( $channels ) ) {
				return Error::invalid_request( \__( 'Missing required parameter: channels (array)', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_order_channels', null, $channels, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides channel ordering.', 'microsub' ) );
			}

			return new \WP_REST_Response( array( 'channels' => $result ), 200 );
		}

		return Error::invalid_request(
			\sprintf(
				/* translators: %s: Method name */
				\__( 'Unknown method: %s', 'microsub' ),
				$method
			)
		);
	}

	/**
	 * Get timeline entries.
	 *
	 * Aggregates timeline items from all registered adapters.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function get_timeline( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );

		if ( empty( $channel ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: channel', 'microsub' ) );
		}

		$args = array(
			'after'  => $request->get_param( 'after' ),
			'before' => $request->get_param( 'before' ),
			'limit'  => $request->get_param( 'limit' ) ? \absint( $request->get_param( 'limit' ) ) : 20,
		);

		// Start with empty result to aggregate from multiple adapters.
		$result = \apply_filters( 'microsub_get_timeline', array( 'items' => array() ), $channel, $args );

		if ( empty( $result['items'] ) ) {
			return new \WP_REST_Response( array( 'items' => array() ), 200 );
		}

		// Sort items by published date (newest first).
		$items = $result['items'];
		\usort(
			$items,
			function ( $a, $b ) {
				$date_a = isset( $a['published'] ) ? \strtotime( $a['published'] ) : 0;
				$date_b = isset( $b['published'] ) ? \strtotime( $b['published'] ) : 0;
				return $date_b - $date_a;
			}
		);

		// Apply limit.
		$items = \array_slice( $items, 0, $args['limit'] );

		$response = array( 'items' => $items );

		if ( isset( $result['paging'] ) ) {
			$response['paging'] = $result['paging'];
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle POST requests for timeline.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function handle_timeline_post( $request, $user_id ) {
		$method  = $request->get_param( 'method' );
		$channel = $request->get_param( 'channel' );

		if ( empty( $channel ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: channel', 'microsub' ) );
		}

		if ( 'mark_read' === $method ) {
			$entries         = $request->get_param( 'entry' );
			$last_read_entry = $request->get_param( 'last_read_entry' );
			$entries         = $entries ?: $last_read_entry;

			if ( empty( $entries ) ) {
				return Error::invalid_request( \__( 'Missing required parameter: entry or last_read_entry', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_timeline_mark_read', null, $channel, $entries, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides mark read support.', 'microsub' ) );
			}

			return new \WP_REST_Response( null, 200 );
		}

		if ( 'mark_unread' === $method ) {
			$entries = $request->get_param( 'entry' );

			if ( empty( $entries ) ) {
				return Error::invalid_request( \__( 'Missing required parameter: entry', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_timeline_mark_unread', null, $channel, $entries, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides mark unread support.', 'microsub' ) );
			}

			return new \WP_REST_Response( null, 200 );
		}

		if ( 'remove' === $method ) {
			$entries = $request->get_param( 'entry' );

			if ( empty( $entries ) ) {
				return Error::invalid_request( \__( 'Missing required parameter: entry', 'microsub' ) );
			}

			$result = \apply_filters( 'microsub_timeline_remove', null, $channel, $entries, $user_id );

			if ( null === $result ) {
				return Error::not_implemented( \__( 'No adapter provides entry removal.', 'microsub' ) );
			}

			return new \WP_REST_Response( null, 200 );
		}

		return Error::invalid_request(
			\sprintf(
				/* translators: %s: Method name */
				\__( 'Unknown method: %s', 'microsub' ),
				$method
			)
		);
	}

	/**
	 * Get followed feeds.
	 *
	 * Aggregates followed feeds from all registered adapters.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function get_following( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );

		if ( empty( $channel ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: channel', 'microsub' ) );
		}

		// Start with empty array to aggregate from multiple adapters.
		$result = \apply_filters( 'microsub_get_following', array(), $channel, $user_id );

		return new \WP_REST_Response( array( 'items' => $result ), 200 );
	}

	/**
	 * Follow a URL.
	 *
	 * The first adapter that can handle the URL (via can_handle_url) will process it.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function follow( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );
		$url     = $request->get_param( 'url' );

		if ( empty( $channel ) || empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameters: channel, url', 'microsub' ) );
		}

		// Adapters should check can_handle_url() and return null if they can't handle it.
		$result = \apply_filters( 'microsub_follow', null, $channel, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter can handle this URL.', 'microsub' ) );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Unfollow a URL.
	 *
	 * The adapter that owns the feed (via owns_feed) will process the unfollow.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function unfollow( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );
		$url     = $request->get_param( 'url' );

		if ( empty( $channel ) || empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameters: channel, url', 'microsub' ) );
		}

		// Adapters should check owns_feed() and return null if they don't own it.
		$result = \apply_filters( 'microsub_unfollow', null, $channel, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter owns this feed.', 'microsub' ) );
		}

		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Get muted users.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function get_muted( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );

		if ( empty( $channel ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: channel', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_get_muted', null, $channel, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides mute support.', 'microsub' ) );
		}

		return new \WP_REST_Response( array( 'items' => $result ), 200 );
	}

	/**
	 * Mute a user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function mute( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );
		$url     = $request->get_param( 'url' );

		if ( empty( $channel ) || empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameters: channel, url', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_mute', null, $channel, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides mute support.', 'microsub' ) );
		}

		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Unmute a user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function unmute( $request, $user_id ) {
		$channel = $request->get_param( 'channel' );
		$url     = $request->get_param( 'url' );

		if ( empty( $channel ) || empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameters: channel, url', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_unmute', null, $channel, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides unmute support.', 'microsub' ) );
		}

		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Get blocked users.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function get_blocked( $request, $user_id ) {
		$channel = $request->get_param( 'channel' ) ?: 'global';

		$result = \apply_filters( 'microsub_get_blocked', null, $channel, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides block support.', 'microsub' ) );
		}

		return new \WP_REST_Response( array( 'items' => $result ), 200 );
	}

	/**
	 * Block a user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function block( $request, $user_id ) {
		$channel = $request->get_param( 'channel' ) ?: 'global';
		$url     = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: url', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_block', null, $channel, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides block support.', 'microsub' ) );
		}

		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Unblock a user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function unblock( $request, $user_id ) {
		$channel = $request->get_param( 'channel' ) ?: 'global';
		$url     = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: url', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_unblock', null, $channel, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides unblock support.', 'microsub' ) );
		}

		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Search for feeds or content.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function search( $request, $user_id ) {
		$query = $request->get_param( 'query' );

		if ( empty( $query ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: query', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_search', null, $query, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides search support.', 'microsub' ) );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Preview a URL.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id The user ID.
	 * @return \WP_REST_Response The response.
	 */
	protected function preview( $request, $user_id ) {
		$url = $request->get_param( 'url' );

		if ( empty( $url ) ) {
			return Error::invalid_request( \__( 'Missing required parameter: url', 'microsub' ) );
		}

		$result = \apply_filters( 'microsub_preview', null, $url, $user_id );

		if ( null === $result ) {
			return Error::not_implemented( \__( 'No adapter provides preview support.', 'microsub' ) );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @return array Query parameters for the collection.
	 */
	public function get_collection_params() {
		return array(
			'action'  => array(
				'description' => \__( 'The Microsub action to perform.', 'microsub' ),
				'type'        => 'string',
				'required'    => true,
				'enum'        => array( 'channels', 'timeline', 'follow', 'mute', 'block', 'search', 'preview' ),
			),
			'channel' => array(
				'description' => \__( 'The channel UID.', 'microsub' ),
				'type'        => 'string',
			),
			'after'   => array(
				'description' => \__( 'Pagination cursor for entries after this point.', 'microsub' ),
				'type'        => 'string',
			),
			'before'  => array(
				'description' => \__( 'Pagination cursor for entries before this point.', 'microsub' ),
				'type'        => 'string',
			),
			'query'   => array(
				'description' => \__( 'Search query string.', 'microsub' ),
				'type'        => 'string',
			),
			'url'     => array(
				'description' => \__( 'URL to preview.', 'microsub' ),
				'type'        => 'string',
				'format'      => 'uri',
			),
		);
	}
}
