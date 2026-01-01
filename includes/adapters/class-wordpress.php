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
	protected $id = 'WordPress';

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

		$channels[] = array(
			'uid'  => 'wp-planet',
			'name' => \__( 'Planet WordPress', 'microsub' ),
		);

		$rss_widgets = $this->get_rss_widgets();
		foreach ( $rss_widgets as $widget ) {
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

		if ( 'wp-planet' === $channel ) {
			$planet_url      = $this->get_planet_feed_url();
			$result['items'] = \array_merge( $result['items'], $this->get_feed_items( $planet_url, $limit, $channel ) );
			return $result;
		}

		if ( 'wp-dashboard' === $channel ) {
			$news_items      = $this->get_news_items( $limit );
			$events          = $this->get_events_items( $limit );
			$combined        = $this->dedupe_items_by_id( \array_merge( $news_items, $events ) );
			$combined        = $this->sort_items_by_date( $combined );
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
	 * Get the Planet WordPress feed URL.
	 *
	 * Uses __() with 'default' domain so translators can provide a localized
	 * planet feed URL, matching WordPress core's dashboard_secondary_feed.
	 *
	 * @return string
	 */
	protected function get_planet_feed_url() {
		// Translators: Link to the Planet feed of the locale.
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomainDefault -- Using WP Core translation.
		return \__( 'https://planet.wordpress.org/feed/' );
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
			$url     = $event['url'] ?? '';
			$time    = $event['date'] ?? ( $event['start'] ?? '' );
			$items[] = array(
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
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$locale = \function_exists( 'get_user_locale' ) ? \get_user_locale() : 'en_US';
		$tz     = \function_exists( 'wp_timezone_string' ) ? \wp_timezone_string() : '';
		$base   = 'https://api.wordpress.org/events/1.0/';
		$params = array(
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
	 * Get custom dashboard RSS feeds.
	 *
	 * Collects feeds from the 'microsub_dashboard_feeds' filter.
	 * Plugins can use this to expose their dashboard RSS widgets.
	 *
	 * @return array
	 */
	protected function get_rss_widgets() {
		/**
		 * Filter to register dashboard RSS feeds with Microsub.
		 *
		 * Plugins can use this to expose their dashboard RSS widgets.
		 *
		 * Example:
		 * add_filter( 'microsub_dashboard_feeds', function( $feeds ) {
		 *     $feeds[] = array(
		 *         'id'   => 'my_plugin_news',
		 *         'name' => __( 'My Plugin News', 'my-plugin' ),
		 *         'url'  => 'https://example.com/feed/',
		 *     );
		 *     return $feeds;
		 * } );
		 *
		 * @param array $feeds Array of feeds, each with 'id', 'name', and 'url' keys.
		 */
		$feeds = \apply_filters( 'microsub_dashboard_feeds', array() );

		if ( ! \is_array( $feeds ) ) {
			return array();
		}

		$widgets = array();

		foreach ( $feeds as $feed ) {
			if ( ! empty( $feed['id'] ) && ! empty( $feed['url'] ) ) {
				$widgets[] = array(
					'id'   => $feed['id'],
					'name' => ! empty( $feed['name'] ) ? $feed['name'] : $feed['id'],
					'url'  => $feed['url'],
				);
			}
		}

		return $widgets;
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
