<?php
/**
 * WordPress Core Feeds Adapter for Microsub.
 *
 * @package Microsub
 */

namespace Microsub\Adapters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Microsub\Adapter;
use Microsub\Utils;

/**
 * WordPress Core Adapter.
 *
 * Provides channels for local WordPress content and core WordPress news/events feeds.
 */
class WordPress extends Adapter {

	/**
	 * Channel UID constants.
	 */
	const CHANNEL_LOCAL_POSTS = 'local-posts';
	const CHANNEL_DASHBOARD   = 'wp-dashboard';
	const CHANNEL_PLANET      = 'wp-planet';
	const CHANNEL_RSS_PREFIX  = 'wp-rss-';

	/**
	 * Cache duration constants (in seconds).
	 */
	const CACHE_DURATION_FEED   = 2 * HOUR_IN_SECONDS;
	const CACHE_DURATION_EVENTS = HOUR_IN_SECONDS;

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
	protected $name = 'WordPress';

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
		// Local blog posts channel.
		$channels[] = array(
			'uid'  => self::CHANNEL_LOCAL_POSTS,
			'name' => \get_bloginfo( 'name' ) ?: \__( 'Local Posts', 'microsub' ),
		);

		// WordPress.org news and events.
		$channels[] = array(
			'uid'  => self::CHANNEL_DASHBOARD,
			'name' => \__( 'WordPress Events and News', 'microsub' ),
		);

		// Planet WordPress community feed.
		$channels[] = array(
			'uid'  => self::CHANNEL_PLANET,
			'name' => \__( 'Planet WordPress', 'microsub' ),
		);

		// Custom RSS widgets from plugins.
		$rss_widgets = $this->get_rss_widgets();
		foreach ( $rss_widgets as $widget ) {
			$channels[] = array(
				'uid'  => self::CHANNEL_RSS_PREFIX . $widget['id'],
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

		// Local blog posts.
		if ( self::CHANNEL_LOCAL_POSTS === $channel ) {
			$items           = $this->get_local_posts( $limit, $args );
			$result['items'] = \array_merge( $result['items'], $items );
			return $result;
		}

		// Custom RSS widget feeds.
		if ( \str_starts_with( $channel, self::CHANNEL_RSS_PREFIX ) ) {
			$feed_url = $this->get_rss_channel_feed( $channel );
			if ( $feed_url ) {
				$result['items'] = \array_merge( $result['items'], $this->get_cached_feed_items( $feed_url, $limit, $channel ) );
			}
			return $result;
		}

		// Planet WordPress feed.
		if ( self::CHANNEL_PLANET === $channel ) {
			$planet_url      = $this->get_planet_feed_url();
			$result['items'] = \array_merge( $result['items'], $this->get_cached_feed_items( $planet_url, $limit, $channel ) );
			return $result;
		}

		// WordPress.org dashboard (news + events).
		if ( self::CHANNEL_DASHBOARD === $channel ) {
			$news_items      = $this->get_cached_news_items( $limit );
			$events          = $this->get_cached_events_items( $limit );
			$combined        = Utils::merge_and_sort_items( \array_merge( $news_items, $events ) );
			$result['items'] = \array_merge( $result['items'], \array_slice( $combined, 0, $limit ) );
		}

		return $result;
	}

	/**
	 * Get list of followed feeds.
	 *
	 * @param array  $result  Current result from other adapters.
	 * @param string $channel Channel UID.
	 * @param int    $user_id The user ID.
	 * @return array Array of feed objects with 'type' and 'url'.
	 */
	public function get_following( $result, $channel, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Local posts channel shows the site's own feed URL.
		if ( self::CHANNEL_LOCAL_POSTS === $channel ) {
			$result[] = array(
				'type' => 'feed',
				'url'  => \get_bloginfo( 'rss2_url' ),
				'name' => \get_bloginfo( 'name' ),
			);
		}

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
		return $result;
	}

	/**
	 * Search for local posts.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $query   Search query.
	 * @param int        $user_id The user ID.
	 * @return array|null Search results.
	 */
	public function search( $result, $query, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$posts = \get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				's'              => $query,
				'posts_per_page' => 10,
			)
		);

		if ( empty( $posts ) ) {
			return $result;
		}

		$results = array();
		foreach ( $posts as $post ) {
			$results[] = array(
				'type' => 'feed',
				'url'  => \get_permalink( $post ),
				'name' => \get_the_title( $post ),
			);
		}

		return array( 'results' => $results );
	}

	/**
	 * Preview a local URL.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $url     URL to preview.
	 * @param int        $user_id The user ID.
	 * @return array|null Preview data.
	 */
	public function preview( $result, $url, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Check if URL is from this site.
		$site_url = \trailingslashit( \home_url() );
		if ( \strpos( $url, $site_url ) !== 0 ) {
			return $result;
		}

		$post_id = \url_to_postid( $url );
		if ( ! $post_id ) {
			return $result;
		}

		$post = \get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return $result;
		}

		return array(
			'items' => array( Utils::post_to_jf2( $post ) ),
		);
	}

	/**
	 * Get local blog posts.
	 *
	 * @param int   $limit Maximum items to return.
	 * @param array $args  Query arguments with 'after' and 'before' cursors.
	 * @return array Array of jf2 items.
	 */
	protected function get_local_posts( $limit, $args ) {
		$query_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Cursor-based pagination.
		if ( ! empty( $args['after'] ) ) {
			$query_args['date_query'] = array(
				array( 'before' => $args['after'] ),
			);
		}

		if ( ! empty( $args['before'] ) ) {
			$query_args['date_query'] = array(
				array( 'after' => $args['before'] ),
			);
			$query_args['order']      = 'ASC';
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = Utils::post_to_jf2( $post );
		}

		// Reverse if we queried ascending for 'before' cursor.
		if ( ! empty( $args['before'] ) ) {
			$items = \array_reverse( $items );
		}

		return $items;
	}

	/**
	 * Get cached news items.
	 *
	 * @param int $limit Maximum items to return.
	 * @return array
	 */
	protected function get_cached_news_items( $limit ) {
		$cache_key = 'microsub_news_' . \md5( $this->get_news_feed_url() );
		$items     = \get_transient( $cache_key );

		if ( false === $items ) {
			$items          = $this->get_news_items( $limit * 2 ); // Fetch more for cache.
			$cache_duration = $this->get_cache_duration( 'news' );
			\set_transient( $cache_key, $items, $cache_duration );
		}

		return \array_slice( $items, 0, $limit );
	}

	/**
	 * Get cached events items.
	 *
	 * @param int $limit Maximum items to return.
	 * @return array
	 */
	protected function get_cached_events_items( $limit ) {
		$user_id   = \get_current_user_id();
		$cache_key = 'microsub_events_' . $user_id;
		$items     = \get_transient( $cache_key );

		if ( false === $items ) {
			$items          = $this->get_events_items( $limit * 2 ); // Fetch more for cache.
			$cache_duration = $this->get_cache_duration( 'events' );
			\set_transient( $cache_key, $items, $cache_duration );
		}

		return \array_slice( $items, 0, $limit );
	}

	/**
	 * Get cached feed items.
	 *
	 * @param string $feed_url Feed URL.
	 * @param int    $limit    Maximum items.
	 * @param string $channel  Channel UID for item IDs.
	 * @return array
	 */
	protected function get_cached_feed_items( $feed_url, $limit, $channel ) {
		$cache_key = 'microsub_feed_' . \md5( $feed_url );
		$items     = \get_transient( $cache_key );

		if ( false === $items ) {
			$items          = $this->get_feed_items( $feed_url, $limit * 2, $channel );
			$cache_duration = $this->get_cache_duration( 'feed' );
			\set_transient( $cache_key, $items, $cache_duration );
		}

		return \array_slice( $items, 0, $limit );
	}

	/**
	 * Get cache duration for a feed type.
	 *
	 * @param string $type Feed type: 'news', 'events', or 'feed'.
	 * @return int Cache duration in seconds.
	 */
	protected function get_cache_duration( $type ) {
		$durations = array(
			'news'   => self::CACHE_DURATION_FEED,
			'events' => self::CACHE_DURATION_EVENTS,
			'feed'   => self::CACHE_DURATION_FEED,
		);

		$duration = isset( $durations[ $type ] ) ? $durations[ $type ] : self::CACHE_DURATION_FEED;

		/**
		 * Filters the cache duration for Microsub feeds.
		 *
		 * @param int    $duration Cache duration in seconds.
		 * @param string $type     Feed type: 'news', 'events', or 'feed'.
		 */
		return \apply_filters( 'microsub_cache_duration', $duration, $type );
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
			$author  = $item->get_author();

			$entry = array(
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

			if ( $author ) {
				$entry['author'] = array(
					'type' => 'card',
					'name' => $author->get_name(),
					'url'  => $author->get_link() ?: 'https://wordpress.org',
				);
			}

			$list[] = $entry;
		}

		return $list;
	}

	/**
	 * Resolve the localized news feed URL based on the site locale.
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

		$user_id  = \get_current_user_id();
		$location = $this->get_events_location( $user_id );
		$response = \wp_get_community_events(
			array(
				'number'   => $limit,
				'location' => $location,
			)
		);

		if ( \is_wp_error( $response ) || empty( $response['events'] ) ) {
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
	 * Resolve the community events location.
	 *
	 * @param int $user_id Current user ID.
	 * @return array Location array.
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
			$location = \wp_get_user_location();
		}

		/**
		 * Filters the location used for WordPress Events.
		 *
		 * @param array $location Location array.
		 * @param int   $user_id  Current user ID.
		 */
		return \apply_filters( 'microsub_events_location', $location, $user_id );
	}

	/**
	 * Fetch events directly from the WordPress.org API.
	 *
	 * @param int   $limit    Max events.
	 * @param array $location Location data.
	 * @return array|\WP_Error Response array or WP_Error.
	 */
	protected function fetch_events_via_api( $limit, $location ) {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$locale = \function_exists( 'get_user_locale' ) ? \get_user_locale() : 'en_US';
		$tz     = \function_exists( 'wp_timezone_string' ) ? \wp_timezone_string() : '';
		$base   = 'https://api.wordpress.org/events/1.0/';

		$query_args = array(
			'number'   => $limit,
			'locale'   => $locale,
			'timezone' => $tz,
		);

		// Add location data.
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
		}

		// Add IP for geo-lookup fallback.
		if ( $ip ) {
			$query_args['ip'] = $ip;
		}

		/**
		 * Filters the timeout for events API requests.
		 *
		 * @param int $timeout Timeout in seconds.
		 */
		$timeout = \apply_filters( 'microsub_events_api_timeout', 8 );

		$url      = \add_query_arg( \array_filter( $query_args ), $base );
		$response = \wp_remote_get( $url, array( 'timeout' => $timeout ) );

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
	 * Get custom dashboard RSS feeds from plugins.
	 *
	 * @return array
	 */
	protected function get_rss_widgets() {
		/**
		 * Filters the list of dashboard RSS feeds.
		 *
		 * Plugins can use this to expose their dashboard RSS widgets.
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
			if ( self::CHANNEL_RSS_PREFIX . $widget['id'] === $uid ) {
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
	 * @param string $channel  Channel UID for item IDs.
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
			$author  = $item->get_author();

			$entry = array(
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

			if ( $author ) {
				$entry['author'] = array(
					'type' => 'card',
					'name' => $author->get_name(),
				);
			}

			$list[] = $entry;
		}

		return $list;
	}
}
