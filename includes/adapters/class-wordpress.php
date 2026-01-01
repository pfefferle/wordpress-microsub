<?php
/**
 * WordPress Core Feeds Adapter for Microsub.
 *
 * @package Microsub
 */

namespace Microsub\Adapters;

use Microsub\Adapter;

/**
 * WordPress Core Adapter.
 *
 * Provides read-only channels for core WordPress news and events feeds.
 */
class WordPress extends Adapter {

	/**
	 * Adapter identifier.
	 *
	 * @var string
	 */
	protected $id = 'wordpress';

	/**
	 * Adapter name.
	 *
	 * @var string
	 */
	protected $name = 'WordPress Core';

	/**
	 * News feed URL.
	 *
	 * @var string
	 */
	protected $news_feed = 'https://wordpress.org/news/feed/';

	/**
	 * Cached dashboard RSS widgets.
	 *
	 * @var array|null
	 */
	protected $rss_widgets = null;

	/**
	 * Get list of channels.
	 *
	 * @param array $channels Current channels array from other adapters.
	 * @param int   $user_id  The user ID.
	 * @return array Array of channels with 'uid' and 'name'.
	 */
	public function get_channels( $channels, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$channels[] = array(
			'uid'  => 'wp-dashboard',
			'name' => \__( 'WordPress Events and News', 'microsub' ),
		);

		foreach ( $this->get_rss_widgets() as $widget ) {
			$channels[] = array(
				'uid'  => 'wp-rss-' . $widget['id'],
				'name' => $widget['name'],
			);
		}

		return $channels;
	}

	/**
	 * Get timeline entries for a channel.
	 *
	 * @param array  $result  Current result with 'items' from other adapters.
	 * @param string $channel Channel UID.
	 * @param array  $args    Query arguments (after, before, limit).
	 * @return array Timeline data with 'items' and optional 'paging'.
	 */
	public function get_timeline( $result, $channel, $args ) {
		$limit = isset( $args['limit'] ) ? \absint( $args['limit'] ) : 20;

		if ( \str_starts_with( $channel, 'wp-rss-' ) ) {
			$feed_url = $this->get_rss_channel_feed( $channel );
			if ( $feed_url ) {
				$result['items'] = \array_merge( $result['items'], $this->get_feed_items( $feed_url, $limit, $channel ) );
			}
			return $result;
		}

		if ( 'wp-dashboard' === $channel ) {
			$news_items = $this->get_news_items( $limit );
			$events     = $this->get_events_items( $limit );
			$combined   = $this->dedupe_items_by_id( \array_merge( $news_items, $events ) );
			$combined   = $this->sort_items_by_date( $combined );
			$result['items'] = \array_merge( $result['items'], \array_slice( $combined, 0, $limit ) );
		}

		return $result;
	}

	/**
	 * Get list of followed feeds (none for core channels).
	 *
	 * @param array  $result  Current result from other adapters.
	 * @param string $channel Channel UID.
	 * @param int    $user_id The user ID.
	 * @return array Array of feed objects with 'type' and 'url'.
	 */
	public function get_following( $result, $channel, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// These channels are read-only; nothing to follow.
		return $result;
	}

	/**
	 * Follow a URL (not supported for core feeds).
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $channel Channel UID.
	 * @param string     $url     URL to follow.
	 * @param int        $user_id The user ID.
	 * @return array|null
	 */
	public function follow( $result, $channel, $url, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Read-only adapter; pass through.
		return $result;
	}

	/**
	 * Unfollow a URL (not supported for core feeds).
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID.
	 * @param string    $url     URL to unfollow.
	 * @param int       $user_id The user ID.
	 * @return bool|null
	 */
	public function unfollow( $result, $channel, $url, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Read-only adapter; pass through.
		return $result;
	}

	/**
	 * Read WordPress.org news feed items.
	 *
	 * @param int $limit Maximum items to return.
	 * @return array
	 */
	protected function get_news_items( $limit ) {
		if ( ! \function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$feed_url = $this->get_news_feed_url();
		$feed     = \fetch_feed( $feed_url );

		if ( \is_wp_error( $feed ) ) {
			return array();
		}

		$max   = $feed->get_item_quantity( $limit );
		$items = $feed->get_items( 0, $max );
		$list  = array();

		foreach ( $items as $item ) {
			$url     = $item->get_permalink();
			$content = $item->get_content();
			$list[]  = array(
				'type'      => 'entry',
				'_id'       => 'news-' . \md5( $url ),
				'name'      => $item->get_title(),
				'url'       => $url,
				'published' => $item->get_date( \DATE_ATOM ),
				'content'   => array(
					'html' => $content,
					'text' => \wp_strip_all_tags( $content ),
				),
			);
		}

		return $list;
	}

	/**
	 * Resolve the localized news feed URL based on the site locale.
	 *
	 * Mirrors the dashboard news widget behavior by using locale-specific domains when available.
	 *
	 * @return string
	 */
	protected function get_news_feed_url() {
		$locale = \function_exists( 'get_locale' ) ? \get_locale() : 'en_US';

		if ( empty( $locale ) || 'en_US' === $locale ) {
			return $this->news_feed;
		}

		$subdomain = \strtolower( \str_replace( '_', '-', $locale ) );
		return 'https://' . $subdomain . '.wordpress.org/news/feed/';
	}

	/**
	 * Read WordPress community events.
	 *
	 * @param int $limit Maximum items to return.
	 * @return array
	 */
	protected function get_events_items( $limit ) {
		if ( ! \function_exists( 'wp_get_community_events' ) ) {
			require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		}

		if ( ! \function_exists( 'wp_get_community_events' ) ) {
			return array();
		}

		$user_id = \get_current_user_id();

		$location = $this->get_events_location( $user_id );
		$response = \wp_get_community_events(
			array(
				'number'   => $limit,
				'location' => $location,
			)
		);

		if ( \is_wp_error( $response ) || empty( $response['events'] ) ) {
			// Simple fallback: try the public API once using whatever location data we have.
			$response = $this->fetch_events_via_api( $limit, $location );
		}

		if ( \is_wp_error( $response ) || empty( $response['events'] ) ) {
			return array();
		}

		$items = array();

		foreach ( $response['events'] as $event ) {
			$url       = $event['url'] ?? '';
			$time      = $event['date'] ?? ( $event['start'] ?? '' );
			$items[]   = array(
				'type'      => 'entry',
				'_id'       => 'event-' . \md5( $url ?: ( $event['title'] ?? '' ) . $time ),
				'name'      => $event['title'] ?? '',
				'url'       => $url,
				'published' => $time,
				'content'   => array(
					'text' => $event['description'] ?? '',
				),
			);
		}

		return $items;
	}

	/**
	 * Build a set of possible locations to try when fetching events.
	 *
	 * @param int $user_id Current user ID.
	 * @return array[] Array of location arrays.
	 */
	protected function build_event_locations( $user_id ) {
		// Deprecated in favor of get_events_location + fetch_events_via_api (single location).
		return array();
	}

	/**
	 * Sort items by published date (newest first).
	 *
	 * @param array $items Items to sort.
	 * @return array
	 */
	protected function sort_items_by_date( $items ) {
		\usort(
			$items,
			function ( $a, $b ) {
				$date_a = isset( $a['published'] ) ? \strtotime( $a['published'] ) : 0;
				$date_b = isset( $b['published'] ) ? \strtotime( $b['published'] ) : 0;
				return $date_b - $date_a;
			}
		);

		return $items;
	}

	/**
	 * Deduplicate items by their _id key.
	 *
	 * @param array $items Items to filter.
	 * @return array
	 */
	protected function dedupe_items_by_id( $items ) {
		$unique = array();
		$seen   = array();

		foreach ( $items as $item ) {
			$id = isset( $item['_id'] ) ? $item['_id'] : null;

			if ( $id && isset( $seen[ $id ] ) ) {
				continue;
			}

			if ( $id ) {
				$seen[ $id ] = true;
			}

			$unique[] = $item;
		}

		return $unique;
	}

	/**
	 * Resolve the community events location (user preference or filtered override).
	 *
	 * @param int $user_id Current user ID.
	 * @return array Location array as accepted by wp_get_community_events.
	 */
	protected function get_events_location( $user_id ) {
		$location = array();

		if ( $user_id ) {
			$user_location = \get_user_option( 'community-events-location', $user_id );

			if ( \is_array( $user_location ) ) {
				$location = $user_location;
			}
		}

		if ( empty( $location ) && \function_exists( 'wp_get_user_location' ) ) {
			// Falls back to the same geo-IP lookup core uses for the dashboard widget.
			$location = \wp_get_user_location();
		}

		/**
		 * Filter the location used for WordPress Events.
		 *
		 * @param array $location Location array.
		 * @param int   $user_id  Current user ID.
		 */
		return \apply_filters( 'microsub_events_location', $location, $user_id );
	}

	/**
	 * Direct API fallback to fetch events from api.wordpress.org.
	 *
	 * @param int   $limit    Max events.
	 * @param array $location Location to try.
	 * @return array|\WP_Error Response array with 'events' or WP_Error.
	 */
	protected function fetch_events_via_api( $limit, $location ) {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$locale  = \function_exists( 'get_user_locale' ) ? \get_user_locale() : 'en_US';
		$tz      = \function_exists( 'wp_timezone_string' ) ? \wp_timezone_string() : '';
		$base    = 'https://api.wordpress.org/events/1.0/';
		$params  = array(
			'number'   => $limit,
			'locale'   => $locale,
			'timezone' => $tz,
		);

		$query_args = $params;

		if ( ! empty( $location['latitude'] ) && ! empty( $location['longitude'] ) ) {
			$query_args['latitude']  = $location['latitude'];
			$query_args['longitude'] = $location['longitude'];
		} elseif ( ! empty( $location['city'] ) ) {
			$query_args['location'] = $location['city'];
			if ( ! empty( $location['country'] ) ) {
				$query_args['country'] = $location['country'];
			}
		} elseif ( ! empty( $location['country'] ) ) {
			$query_args['country'] = $location['country'];
		} elseif ( $ip ) {
			$query_args['ip'] = $ip;
		}

		if ( $ip && ! isset( $query_args['ip'] ) ) {
			$query_args['ip'] = $ip;
		}

		$url      = \add_query_arg( array_filter( $query_args ), $base );
		$response = \wp_remote_get( $url, array( 'timeout' => 8 ) );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$code = \wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new \WP_Error( 'events_api_error', 'Events API returned non-200 status.' );
		}

		$body = \wp_remote_retrieve_body( $response );
		$data = \json_decode( $body, true );

		if ( empty( $data['events'] ) || ! \is_array( $data['events'] ) ) {
			return new \WP_Error( 'events_api_empty', 'Events API returned no events.' );
		}

		return array( 'events' => $data['events'] );
	}

	/**
	 * Get custom dashboard RSS widgets that use wp_widget_rss_output.
	 *
	 * @return array
	 */
	protected function get_rss_widgets() {
		if ( null !== $this->rss_widgets ) {
			return $this->rss_widgets;
		}

		if ( ! \is_admin() ) {
			// Ensure dashboard widgets are registered.
			\do_action( 'wp_dashboard_setup' );
		}

		global $wp_meta_boxes;

		$this->rss_widgets = array();
		$options           = \get_option( 'dashboard_widget_options', array() );

		if ( empty( $wp_meta_boxes['dashboard'] ) ) {
			return $this->rss_widgets;
		}

		foreach ( $wp_meta_boxes['dashboard'] as $priority => $boxes ) {
			foreach ( $boxes as $box ) {
				foreach ( $box as $widget_id => $widget ) {
					$callback = isset( $widget['callback'] ) ? $widget['callback'] : null;

					if ( is_array( $callback ) && isset( $callback[1] ) && 'wp_widget_rss_output' === $callback[1] ) {
						$title = isset( $widget['title'] ) ? $widget['title'] : $widget['id'];
						$url   = $options[ $widget_id ]['url'] ?? '';

						if ( $url ) {
							$this->rss_widgets[] = array(
								'id'   => $widget_id,
								'name' => $title,
								'url'  => $url,
							);
						}
					}
				}
			}
		}

		return $this->rss_widgets;
	}

	/**
	 * Map RSS channel UID to feed URL.
	 *
	 * @param string $uid Channel UID.
	 * @return string|null
	 */
	protected function get_rss_channel_feed( $uid ) {
		$widgets = $this->get_rss_widgets();

		foreach ( $widgets as $widget ) {
			if ( 'wp-rss-' . $widget['id'] === $uid ) {
				return $widget['url'];
			}
		}

		return null;
	}

	/**
	 * Read generic RSS/Atom feed items.
	 *
	 * @param string $feed_url Feed URL.
	 * @param int    $limit    Max items.
	 * @param string $channel  Channel UID used for IDs.
	 * @return array
	 */
	protected function get_feed_items( $feed_url, $limit, $channel ) {
		if ( ! \function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$feed = \fetch_feed( $feed_url );

		if ( \is_wp_error( $feed ) ) {
			return array();
		}

		$max   = $feed->get_item_quantity( $limit );
		$items = $feed->get_items( 0, $max );
		$list  = array();

		foreach ( $items as $item ) {
			$url     = $item->get_permalink();
			$content = $item->get_content();
			$list[]  = array(
				'type'      => 'entry',
				'_id'       => $channel . '-' . \md5( $url ),
				'name'      => $item->get_title(),
				'url'       => $url,
				'published' => $item->get_date( \DATE_ATOM ),
				'content'   => array(
					'html' => $content,
					'text' => \wp_strip_all_tags( $content ),
				),
			);
		}

		return $list;
	}
}
