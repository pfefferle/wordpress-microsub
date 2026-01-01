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
			'uid'  => 'wp-news',
			'name' => \__( 'WordPress News', 'microsub' ),
		);

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
		if ( 'wp-dashboard' !== $channel && 'wp-news' !== $channel ) {
			return $result;
		}

		$limit = isset( $args['limit'] ) ? \absint( $args['limit'] ) : 20;

		$news_items = $this->get_news_items( $limit );

		if ( 'wp-news' === $channel ) {
			$result['items'] = \array_merge( $result['items'], $news_items );
			return $result;
		}

		$events     = $this->get_events_items( $limit );
		$combined   = \array_merge( $news_items, $events );
		$combined   = $this->sort_items_by_date( $combined );
		$result['items'] = \array_merge( $result['items'], \array_slice( $combined, 0, $limit ) );

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

		$feed = \fetch_feed( $this->news_feed );

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

		$events_response = \wp_get_community_events(
			array(
				'number' => $limit,
			)
		);

		if ( \is_wp_error( $events_response ) || empty( $events_response['events'] ) ) {
			return array();
		}

		$items = array();

		foreach ( $events_response['events'] as $event ) {
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
}
