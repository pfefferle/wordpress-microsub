<?php
/**
 * ActivityPub Plugin Adapter for Microsub.
 *
 * @package Microsub
 */

namespace Microsub\Adapters;

use Microsub\Adapter;

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
		$actor = \Activitypub\get_remote_metadata_by_actor( $url );

		return ! \is_wp_error( $actor ) && ! empty( $actor );
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

		$actor = $this->get_actor_by_url( $url );
		return ! empty( $actor );
	}

	/**
	 * Get an actor post by URL.
	 *
	 * @param string $url The actor URL.
	 * @return \WP_Post|null The actor post or null.
	 */
	protected function get_actor_by_url( $url ) {
		$actors = \get_posts(
			array(
				'post_type'      => \Activitypub\Collection\Remote_Actors::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_activitypub_actor_id',
						'value' => $url,
					),
					array(
						'key'   => '_activitypub_canonical_url',
						'value' => $url,
					),
				),
			)
		);

		return ! empty( $actors ) ? $actors[0] : null;
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

		$limit = isset( $args['limit'] ) ? \absint( $args['limit'] ) : 20;

		// Get posts from inbox.
		$query_args = array(
			'post_type'      => \Activitypub\Collection\Inbox::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Handle cursor-based pagination.
		if ( ! empty( $args['after'] ) ) {
			$query_args['date_query'] = array(
				array(
					'before' => $args['after'],
				),
			);
		}

		if ( ! empty( $args['before'] ) ) {
			$query_args['date_query'] = array(
				array(
					'after' => $args['before'],
				),
			);
			$query_args['order'] = 'ASC';
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

		// Get followed actors.
		$actors = \get_posts(
			array(
				'post_type'      => \Activitypub\Collection\Remote_Actors::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => \Activitypub\Collection\Following::FOLLOWING_META_KEY,
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			)
		);

		foreach ( $actors as $actor ) {
			$actor_id = \get_post_meta( $actor->ID, '_activitypub_actor_id', true );

			$feed = array(
				'type' => 'feed',
				'url'  => $actor_id ?: $actor->guid,
				'_id'  => \md5( $actor_id ?: $actor->guid ),
			);

			if ( $actor->post_title ) {
				$feed['name'] = $actor->post_title;
			}

			$icon = \get_post_meta( $actor->ID, '_activitypub_icon', true );
			if ( $icon ) {
				$feed['photo'] = \is_array( $icon ) ? ( $icon['url'] ?? '' ) : $icon;
			}

			$result[] = $feed;
		}

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

		// Check if we can handle this URL.
		if ( ! $this->can_handle_url( $url ) ) {
			return $result; // Pass to next adapter.
		}

		// Use ActivityPub follow function.
		if ( \function_exists( '\Activitypub\follow' ) ) {
			$follow_result = \Activitypub\follow( $url, $user_id );

			if ( ! \is_wp_error( $follow_result ) ) {
				return array(
					'type' => 'feed',
					'url'  => $url,
				);
			}
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

		// Check if we own this feed.
		$actor = $this->get_actor_by_url( $url );

		if ( ! $actor ) {
			return $result; // Pass to next adapter.
		}

		// Use ActivityPub unfollow function.
		if ( \function_exists( '\Activitypub\unfollow' ) ) {
			$unfollow_result = \Activitypub\unfollow( $actor->ID, $user_id );

			if ( ! \is_wp_error( $unfollow_result ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert an inbox post to jf2 format.
	 *
	 * @param \WP_Post $post The post object.
	 * @return array|null jf2 formatted entry or null.
	 */
	protected function post_to_jf2( $post ) {
		$activity = \get_post_meta( $post->ID, '_activitypub_activity', true );

		if ( empty( $activity ) ) {
			return null;
		}

		$object = $activity['object'] ?? $activity;

		$jf2 = array(
			'type'      => 'entry',
			'_id'       => (string) $post->ID,
			'published' => \get_the_date( 'c', $post ),
		);

		// URL.
		if ( ! empty( $object['url'] ) ) {
			$jf2['url'] = \is_array( $object['url'] ) ? $object['url'][0] : $object['url'];
		} elseif ( ! empty( $object['id'] ) ) {
			$jf2['url'] = $object['id'];
		}

		// Name/Title.
		if ( ! empty( $object['name'] ) ) {
			$jf2['name'] = $object['name'];
		}

		// Content.
		if ( ! empty( $object['content'] ) ) {
			$jf2['content'] = array(
				'html' => $object['content'],
				'text' => \wp_strip_all_tags( $object['content'] ),
			);
		} elseif ( ! empty( $object['summary'] ) ) {
			$jf2['content'] = array(
				'text' => $object['summary'],
			);
		}

		// Author.
		$actor = $object['attributedTo'] ?? ( $activity['actor'] ?? null );
		if ( $actor ) {
			$actor_data = \is_string( $actor )
				? \Activitypub\get_remote_metadata_by_actor( $actor )
				: $actor;

			if ( ! \is_wp_error( $actor_data ) && ! empty( $actor_data ) ) {
				$jf2['author'] = array(
					'type' => 'card',
					'name' => $actor_data['name'] ?? ( $actor_data['preferredUsername'] ?? '' ),
					'url'  => $actor_data['url'] ?? ( $actor_data['id'] ?? '' ),
				);

				if ( ! empty( $actor_data['icon'] ) ) {
					$icon = $actor_data['icon'];
					$jf2['author']['photo'] = \is_array( $icon ) ? ( $icon['url'] ?? '' ) : $icon;
				}
			}
		}

		// Images.
		if ( ! empty( $object['attachment'] ) ) {
			foreach ( $object['attachment'] as $attachment ) {
				if ( isset( $attachment['mediaType'] ) && \str_starts_with( $attachment['mediaType'], 'image/' ) ) {
					$jf2['photo'][] = $attachment['url'] ?? '';
				}
			}
		}

		return $jf2;
	}
}
