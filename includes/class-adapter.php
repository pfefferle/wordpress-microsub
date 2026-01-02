<?php
/**
 * Microsub Adapter Abstract Class.
 *
 * @package Microsub
 */

namespace Microsub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class Adapter
 *
 * Base adapter that reader plugins can extend to provide Microsub functionality.
 * Multiple adapters can be registered and will aggregate their results.
 *
 * Required methods (abstract):
 * - get_channels()
 * - get_timeline()
 * - get_following()
 * - follow()
 * - unfollow()
 *
 * Optional methods (override if supported):
 * - can_handle_url() - Return true if this adapter can follow the given URL
 * - owns_feed() - Return true if this adapter owns/manages the given feed URL
 * - create_channel(), update_channel(), delete_channel(), order_channels()
 * - timeline_mark_read(), timeline_mark_unread(), timeline_remove()
 * - get_muted(), mute(), unmute()
 * - get_blocked(), block(), unblock()
 * - search(), preview()
 */
abstract class Adapter {

	/**
	 * Adapter identifier.
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * Adapter name.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Adapter priority for filters.
	 *
	 * @var int
	 */
	protected $priority = 10;

	/**
	 * Register the adapter hooks.
	 */
	public function register() {
		// Channel operations.
		\add_filter( 'microsub_get_channels', array( $this, 'get_channels' ), $this->priority, 2 );
		\add_filter( 'microsub_create_channel', array( $this, 'create_channel' ), $this->priority, 3 );
		\add_filter( 'microsub_update_channel', array( $this, 'update_channel' ), $this->priority, 4 );
		\add_filter( 'microsub_delete_channel', array( $this, 'delete_channel' ), $this->priority, 3 );
		\add_filter( 'microsub_order_channels', array( $this, 'order_channels' ), $this->priority, 3 );

		// Timeline operations.
		\add_filter( 'microsub_get_timeline', array( $this, 'get_timeline' ), $this->priority, 3 );
		\add_filter( 'microsub_timeline_mark_read', array( $this, 'timeline_mark_read' ), $this->priority, 4 );
		\add_filter( 'microsub_timeline_mark_unread', array( $this, 'timeline_mark_unread' ), $this->priority, 4 );
		\add_filter( 'microsub_timeline_remove', array( $this, 'timeline_remove' ), $this->priority, 4 );

		// Follow operations.
		\add_filter( 'microsub_get_following', array( $this, 'get_following' ), $this->priority, 3 );
		\add_filter( 'microsub_follow', array( $this, 'follow' ), $this->priority, 4 );
		\add_filter( 'microsub_unfollow', array( $this, 'unfollow' ), $this->priority, 4 );

		// Mute operations.
		\add_filter( 'microsub_get_muted', array( $this, 'get_muted' ), $this->priority, 3 );
		\add_filter( 'microsub_mute', array( $this, 'mute' ), $this->priority, 4 );
		\add_filter( 'microsub_unmute', array( $this, 'unmute' ), $this->priority, 4 );

		// Block operations.
		\add_filter( 'microsub_get_blocked', array( $this, 'get_blocked' ), $this->priority, 3 );
		\add_filter( 'microsub_block', array( $this, 'block' ), $this->priority, 4 );
		\add_filter( 'microsub_unblock', array( $this, 'unblock' ), $this->priority, 4 );

		// Search and preview.
		\add_filter( 'microsub_search', array( $this, 'search' ), $this->priority, 3 );
		\add_filter( 'microsub_preview', array( $this, 'preview' ), $this->priority, 3 );

		// Register adapter.
		\add_filter( 'microsub_adapters', array( $this, 'register_adapter' ), $this->priority );

		/**
		 * Fires when an adapter is registered.
		 *
		 * @param Adapter $this The adapter instance.
		 */
		\do_action( 'microsub_adapter_registered', $this );
	}

	/**
	 * Register this adapter in the adapters list.
	 *
	 * @param array $adapters List of registered adapters.
	 * @return array Modified list of adapters.
	 */
	public function register_adapter( $adapters ) {
		$adapters[ $this->id ] = array(
			'id'   => $this->id,
			'name' => $this->name,
		);
		return $adapters;
	}

	/**
	 * Get adapter ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get adapter name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	// =========================================================================
	// URL handling - Override to specify what URLs this adapter handles
	// =========================================================================

	/**
	 * Check if this adapter can handle following the given URL.
	 *
	 * Override this method to specify what types of URLs this adapter supports.
	 * For example, an RSS adapter might check for RSS/Atom feeds,
	 * while an ActivityPub adapter might check for ActivityPub actors.
	 *
	 * @param string $url The URL to check.
	 * @return bool True if this adapter can handle the URL.
	 */
	public function can_handle_url( $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return true;
	}

	/**
	 * Check if this adapter owns/manages the given feed URL.
	 *
	 * Override this method to check if a feed was subscribed through this adapter.
	 * Used for unfollow and other feed-specific operations.
	 *
	 * @param string $url The feed URL to check.
	 * @return bool True if this adapter owns the feed.
	 */
	public function owns_feed( $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return false;
	}

	// =========================================================================
	// REQUIRED: Channel operations
	// =========================================================================

	/**
	 * Get list of channels.
	 *
	 * Results from multiple adapters are merged automatically.
	 * Return only your adapter's channels.
	 *
	 * @param array $channels Current channels array from other adapters.
	 * @param int   $user_id  The user ID.
	 * @return array Array of channels with 'uid' and 'name'.
	 */
	abstract public function get_channels( $channels, $user_id );

	// =========================================================================
	// REQUIRED: Timeline operations
	// =========================================================================

	/**
	 * Get timeline entries for a channel.
	 *
	 * Results from multiple adapters are merged automatically.
	 * Return only your adapter's items.
	 *
	 * @param array  $result  Current result with 'items' from other adapters.
	 * @param string $channel Channel UID.
	 * @param array  $args    Query arguments.
	 * @return array Timeline data with 'items' and optional 'paging'.
	 */
	abstract public function get_timeline( $result, $channel, $args );

	// =========================================================================
	// REQUIRED: Follow operations
	// =========================================================================

	/**
	 * Get list of followed feeds in a channel.
	 *
	 * Results from multiple adapters are merged automatically.
	 * Return only your adapter's feeds.
	 *
	 * @param array  $result  Current result from other adapters.
	 * @param string $channel Channel UID.
	 * @param int    $user_id The user ID.
	 * @return array Array of feed objects with 'type' and 'url'.
	 */
	abstract public function get_following( $result, $channel, $user_id );

	/**
	 * Follow a URL.
	 *
	 * Only called if can_handle_url() returns true for this URL.
	 * First adapter that can handle the URL wins.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $channel Channel UID.
	 * @param string     $url     URL to follow.
	 * @param int        $user_id The user ID.
	 * @return array|null Feed data on success, null to pass to next adapter.
	 */
	abstract public function follow( $result, $channel, $url, $user_id );

	/**
	 * Unfollow a URL.
	 *
	 * Only called if owns_feed() returns true for this URL.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID.
	 * @param string    $url     URL to unfollow.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null to pass to next adapter.
	 */
	abstract public function unfollow( $result, $channel, $url, $user_id );

	// =========================================================================
	// OPTIONAL: Channel management
	// =========================================================================

	/**
	 * Create a new channel.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $name    Channel name.
	 * @param int        $user_id The user ID.
	 * @return array|null Created channel data, or null if not supported.
	 */
	public function create_channel( $result, $name, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $result;
	}

	/**
	 * Update a channel.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $uid     Channel UID.
	 * @param string     $name    New channel name.
	 * @param int        $user_id The user ID.
	 * @return array|null Updated channel data, or null if not supported.
	 */
	public function update_channel( $result, $uid, $name, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $result;
	}

	/**
	 * Delete a channel.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $uid     Channel UID.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function delete_channel( $result, $uid, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $result;
	}

	/**
	 * Reorder channels.
	 *
	 * @param array|null $result   Current result or null.
	 * @param array      $channels Array of channel UIDs in desired order.
	 * @param int        $user_id  The user ID.
	 * @return array|null Reordered channels, or null if not supported.
	 */
	public function order_channels( $result, $channels, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $result;
	}

	// =========================================================================
	// OPTIONAL: Timeline management
	// =========================================================================

	/**
	 * Mark entries as read.
	 *
	 * @param bool|null    $result  Current result or null.
	 * @param string       $channel Channel UID.
	 * @param array|string $entries Entry IDs to mark as read.
	 * @param int          $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function timeline_mark_read( $result, $channel, $entries, $user_id ) {
		return $result;
	}

	/**
	 * Mark entries as unread.
	 *
	 * @param bool|null    $result  Current result or null.
	 * @param string       $channel Channel UID.
	 * @param array|string $entries Entry IDs to mark as unread.
	 * @param int          $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function timeline_mark_unread( $result, $channel, $entries, $user_id ) {
		return $result;
	}

	/**
	 * Remove entries from timeline.
	 *
	 * @param bool|null    $result  Current result or null.
	 * @param string       $channel Channel UID.
	 * @param array|string $entries Entry IDs to remove.
	 * @param int          $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function timeline_remove( $result, $channel, $entries, $user_id ) {
		return $result;
	}

	// =========================================================================
	// OPTIONAL: Mute operations
	// =========================================================================

	/**
	 * Get list of muted users in a channel.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $channel Channel UID.
	 * @param int        $user_id The user ID.
	 * @return array|null Array of muted user URLs, or null if not supported.
	 */
	public function get_muted( $result, $channel, $user_id ) {
		return $result;
	}

	/**
	 * Mute a user.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID.
	 * @param string    $url     User URL to mute.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function mute( $result, $channel, $url, $user_id ) {
		return $result;
	}

	/**
	 * Unmute a user.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID.
	 * @param string    $url     User URL to unmute.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function unmute( $result, $channel, $url, $user_id ) {
		return $result;
	}

	// =========================================================================
	// OPTIONAL: Block operations
	// =========================================================================

	/**
	 * Get list of blocked users.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $channel Channel UID (usually 'global').
	 * @param int        $user_id The user ID.
	 * @return array|null Array of blocked user URLs, or null if not supported.
	 */
	public function get_blocked( $result, $channel, $user_id ) {
		return $result;
	}

	/**
	 * Block a user.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID (usually 'global').
	 * @param string    $url     User URL to block.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function block( $result, $channel, $url, $user_id ) {
		return $result;
	}

	/**
	 * Unblock a user.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID (usually 'global').
	 * @param string    $url     User URL to unblock.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null if not supported.
	 */
	public function unblock( $result, $channel, $url, $user_id ) {
		return $result;
	}

	// =========================================================================
	// OPTIONAL: Search and preview
	// =========================================================================

	/**
	 * Search for feeds or content.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $query   Search query.
	 * @param int        $user_id The user ID.
	 * @return array|null Search results, or null if not supported.
	 */
	public function search( $result, $query, $user_id ) {
		return $result;
	}

	/**
	 * Preview a URL before following.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $url     URL to preview.
	 * @param int        $user_id The user ID.
	 * @return array|null Preview data, or null if not supported.
	 */
	public function preview( $result, $url, $user_id ) {
		return $result;
	}

	// =========================================================================
	// Utility methods
	// =========================================================================

	/**
	 * Convert a post/item to jf2 format.
	 *
	 * @param array $item The item to convert.
	 * @return array jf2 formatted entry.
	 */
	protected function to_jf2( $item ) {
		$jf2 = array(
			'type' => 'entry',
		);

		$field_map = array(
			'id'        => '_id',
			'url'       => 'url',
			'name'      => 'name',
			'content'   => 'content',
			'summary'   => 'summary',
			'published' => 'published',
			'updated'   => 'updated',
			'author'    => 'author',
			'photo'     => 'photo',
			'video'     => 'video',
			'audio'     => 'audio',
		);

		foreach ( $field_map as $source => $target ) {
			if ( isset( $item[ $source ] ) && ! empty( $item[ $source ] ) ) {
				$jf2[ $target ] = $item[ $source ];
			}
		}

		if ( isset( $item['is_read'] ) ) {
			$jf2['_is_read'] = (bool) $item['is_read'];
		}

		/**
		 * Filters the jf2 formatted entry.
		 *
		 * @param array $jf2  The jf2 entry.
		 * @param array $item The original item.
		 */
		return \apply_filters( 'microsub_to_jf2', $jf2, $item );
	}

	/**
	 * Format author data for jf2.
	 *
	 * @param array|string $author Author data.
	 * @return array jf2 formatted author.
	 */
	protected function format_author( $author ) {
		if ( \is_string( $author ) ) {
			return array(
				'type' => 'card',
				'name' => $author,
			);
		}

		$card = array( 'type' => 'card' );

		if ( isset( $author['name'] ) ) {
			$card['name'] = $author['name'];
		}
		if ( isset( $author['url'] ) ) {
			$card['url'] = $author['url'];
		}
		if ( isset( $author['photo'] ) ) {
			$card['photo'] = $author['photo'];
		}

		return $card;
	}
}
