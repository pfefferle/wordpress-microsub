<?php
/**
 * ActivityPub Plugin Adapter for Microsub.
 *
 * @package Microsub
 */

namespace Microsub\Adapters;

use Microsub\Adapter;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Posts;

/**
 * ActivityPub Adapter
 *
 * Provides Microsub functionality using the ActivityPub plugin as backend.
 *
 * @see https://github.com/Automattic/wordpress-activitypub
 */
class ActivityPub extends Adapter {

	/**
	 * Adapter identifier.
	 *
	 * @var string
	 */
	protected $id = 'activitypub';

	/**
	 * Adapter name.
	 *
	 * @var string
	 */
	protected $name = 'ActivityPub';

	/**
	 * Check if the ActivityPub plugin is available.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return \defined( 'ACTIVITYPUB_PLUGIN_VERSION' );
	}

	/**
	 * Check if this adapter can handle following the given URL.
	 *
	 * ActivityPub can handle URLs that resolve to ActivityPub actors.
	 *
	 * @param string $url The URL to check.
	 * @return bool True if this adapter can handle the URL.
	 */
	public function can_handle_url( $url ) {
		if ( ! self::is_available() ) {
			return false;
		}

		// Check if URL resolves to an ActivityPub actor.
		$actor = Remote_Actors::fetch_by_various( $url );

		return ! \is_wp_error( $actor );
	}

	/**
	 * Check if this adapter owns/manages the given feed URL.
	 *
	 * @param string $url The feed URL to check.
	 * @return bool True if this adapter owns the feed.
	 */
	public function owns_feed( $url ) {
		if ( ! self::is_available() ) {
			return false;
		}

		$actor = Remote_Actors::get_by_uri( $url );
		return ! \is_wp_error( $actor );
	}

	/**
	 * Get list of channels.
	 *
	 * @param array $channels Current channels array from other adapters.
	 * @param int   $user_id  The user ID.
	 * @return array Array of channels with 'uid' and 'name'.
	 */
	public function get_channels( $channels, $user_id ) {
		if ( ! self::is_available() ) {
			return $channels;
		}

		// Add ActivityPub channel.
		$channels[] = array(
			'uid'  => 'activitypub',
			'name' => \__( 'ActivityPub', 'microsub' ),
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
		if ( ! self::is_available() ) {
			return $result;
		}

		// Only handle 'activitypub' channel.
		if ( 'activitypub' !== $channel ) {
			return $result;
		}

		$limit   = isset( $args['limit'] ) ? \absint( $args['limit'] ) : 20;
		$user_id = \get_current_user_id();

		$query_args = array(
			'post_type'      => Posts::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_key'       => '_activitypub_user_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		);

		if ( ! empty( $args['after'] ) ) {
			$query_args['date_query'] = array(
				array( 'before' => $args['after'] ),
			);
		}

		if ( ! empty( $args['before'] ) ) {
			$query_args['date_query'] = array(
				array( 'after' => $args['before'] ),
			);
		}

		$query = new \WP_Query( $query_args );

		foreach ( $query->posts as $post ) {
			$item = $this->post_to_jf2( $post );
			if ( $item ) {
				$result['items'][] = $item;
			}
		}

		return $result;
	}

	/**
	 * Get list of followed feeds in a channel.
	 *
	 * @param array  $result  Current result from other adapters.
	 * @param string $channel Channel UID.
	 * @param int    $user_id The user ID.
	 * @return array Array of feed objects with 'type' and 'url'.
	 */
	public function get_following( $result, $channel, $user_id ) {
		if ( ! self::is_available() ) {
			return $result;
		}

		// Only handle 'activitypub' channel.
		if ( 'activitypub' !== $channel ) {
			return $result;
		}

		// Get followed actors using the Following collection.
		$actors = Following::get_many( $user_id );

		foreach ( $actors as $actor_post ) {
			$actor = Remote_Actors::get_actor( $actor_post );

			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$feed = array(
				'type' => 'feed',
				'url'  => $actor->get_id(),
				'_id'  => \md5( $actor->get_id() ),
			);

			$name = $actor->get_name() ?: $actor->get_preferred_username();
			if ( $name ) {
				$feed['name'] = $name;
			}

			$icon = $actor->get_icon();
			if ( $icon ) {
				$feed['photo'] = \is_array( $icon ) ? ( $icon['url'] ?? '' ) : $icon;
			}

			$result[] = $feed;
		}//end foreach

		return $result;
	}

	/**
	 * Follow a URL.
	 *
	 * @param array|null $result  Current result or null.
	 * @param string     $channel Channel UID.
	 * @param string     $url     URL to follow.
	 * @param int        $user_id The user ID.
	 * @return array|null Feed data on success, null to pass to next adapter.
	 */
	public function follow( $result, $channel, $url, $user_id ) {
		if ( ! self::is_available() ) {
			return $result;
		}

		// Check if we can handle this URL. Pass to next adapter if not.
		if ( ! $this->can_handle_url( $url ) ) {
			return $result;
		}

		// Use ActivityPub follow function.
		$follow_result = \Activitypub\follow( $url, $user_id );

		if ( ! \is_wp_error( $follow_result ) ) {
			return array(
				'type' => 'feed',
				'url'  => $url,
			);
		}

		return $result;
	}

	/**
	 * Unfollow a URL.
	 *
	 * @param bool|null $result  Current result or null.
	 * @param string    $channel Channel UID.
	 * @param string    $url     URL to unfollow.
	 * @param int       $user_id The user ID.
	 * @return bool|null True on success, false on failure, null to pass to next adapter.
	 */
	public function unfollow( $result, $channel, $url, $user_id ) {
		if ( ! self::is_available() ) {
			return $result;
		}

		// Check if we own this feed. Pass to next adapter if not.
		$actor = Remote_Actors::get_by_uri( $url );

		if ( \is_wp_error( $actor ) ) {
			return $result;
		}

		// Use ActivityPub unfollow function.
		$unfollow_result = \Activitypub\unfollow( $actor->ID, $user_id );

		if ( ! \is_wp_error( $unfollow_result ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert an ap_post to jf2 format.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array|null jf2 formatted entry or null.
	 */
	protected function post_to_jf2( $post ) {
		$jf2 = array(
			'type'      => 'entry',
			'_id'       => (string) $post->ID,
			'published' => \get_the_date( 'c', $post ),
			'url'       => $post->guid,
		);

		// Name/Title.
		if ( ! empty( $post->post_title ) ) {
			$jf2['name'] = $post->post_title;
		}

		// Content.
		if ( ! empty( $post->post_content ) ) {
			$jf2['content'] = array(
				'html' => $post->post_content,
				'text' => \wp_strip_all_tags( $post->post_content ),
			);
		} elseif ( ! empty( $post->post_excerpt ) ) {
			$jf2['content'] = array(
				'text' => $post->post_excerpt,
			);
		}

		// Author - get from the linked remote actor.
		$remote_actor_id = \get_post_meta( $post->ID, '_activitypub_remote_actor_id', true );
		if ( $remote_actor_id ) {
			$actor = Remote_Actors::get_actor( $remote_actor_id );

			if ( ! \is_wp_error( $actor ) ) {
				$jf2['author'] = array(
					'type' => 'card',
					'name' => $actor->get_name() ?: $actor->get_preferred_username(),
					'url'  => $actor->get_url() ?: $actor->get_id(),
				);

				$icon = $actor->get_icon();
				if ( $icon ) {
					$jf2['author']['photo'] = \is_array( $icon ) ? ( $icon['url'] ?? '' ) : $icon;
				}
			}
		}

		// Images from attachments.
		$attachments = \get_children(
			array(
				'post_parent' => $post->ID,
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
			)
		);

		foreach ( $attachments as $attachment ) {
			if ( \str_starts_with( $attachment->post_mime_type, 'image/' ) ) {
				$jf2['photo'][] = \wp_get_attachment_url( $attachment->ID );
			}
		}

		return $jf2;
	}
}
