<?php
/**
 * Test REST Controller class.
 *
 * @package Microsub
 */

namespace Microsub\Tests;

use WP_REST_Request;
use WP_UnitTestCase;
use Microsub\Rest_Controller;

/**
 * Test REST Controller class.
 *
 * @coversDefaultClass \Microsub\Rest_Controller
 */
class Test_Rest_Controller extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

		$this->user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test that routes are registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/microsub/1.0/endpoint', $routes );
	}

	/**
	 * Test get_channels returns 501 without adapter.
	 *
	 * @covers ::get_channels
	 */
	public function test_get_channels_returns_core_channels() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'channels' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'channels', $data );
		$this->assertNotEmpty( $data['channels'] );
		$uids = wp_list_pluck( $data['channels'], 'uid' );
		$this->assertTrue( in_array( 'wp-dashboard', $uids, true ), 'Expected wp-dashboard channel' );
	}

	/**
	 * Test get_channels returns channels from adapter.
	 *
	 * @covers ::get_channels
	 */
	public function test_get_channels_with_adapter() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_get_channels',
			function () {
				return array(
					array(
						'uid'  => 'test',
						'name' => 'Test Channel',
					),
				);
			}
		);

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'channels' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'channels', $data );
		$this->assertCount( 1, $data['channels'] );
		$this->assertEquals( 'test', $data['channels'][0]['uid'] );
	}

	/**
	 * Test get_timeline returns 400 without channel.
	 *
	 * @covers ::get_timeline
	 */
	public function test_get_timeline_missing_channel() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'timeline' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test get_timeline returns empty items.
	 *
	 * @covers ::get_timeline
	 */
	public function test_get_timeline_empty() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'timeline' );
		$request->set_param( 'channel', 'home' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'items', $data );
		$this->assertEmpty( $data['items'] );
	}

	/**
	 * Test get_timeline returns and sorts items.
	 *
	 * @covers ::get_timeline
	 */
	public function test_get_timeline_with_items() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_get_timeline',
			function ( $result ) {
				$result['items'][] = array(
					'type'      => 'entry',
					'_id'       => 'item-1',
					'published' => '2024-01-15T10:00:00+00:00',
					'url'       => 'https://example.com/1',
				);
				$result['items'][] = array(
					'type'      => 'entry',
					'_id'       => 'item-2',
					'published' => '2024-01-15T11:00:00+00:00',
					'url'       => 'https://example.com/2',
				);
				return $result;
			}
		);

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'timeline' );
		$request->set_param( 'channel', 'home' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data['items'] );
		$this->assertEquals( 'item-2', $data['items'][0]['_id'] );
	}

	/**
	 * Test get_following returns 400 without channel.
	 *
	 * @covers ::get_following
	 */
	public function test_get_following_missing_channel() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'follow' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test follow returns 400 without required params.
	 *
	 * @covers ::follow
	 */
	public function test_follow_missing_params() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'POST', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'follow' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test follow returns feed on success.
	 *
	 * @covers ::follow
	 */
	public function test_follow_success() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_follow',
			function ( $result, $channel, $url ) {
				return array(
					'type' => 'feed',
					'url'  => $url,
				);
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'POST', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'follow' );
		$request->set_param( 'channel', 'home' );
		$request->set_param( 'url', 'https://example.com/feed' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'feed', $data['type'] );
		$this->assertEquals( 'https://example.com/feed', $data['url'] );
	}

	/**
	 * Test unfollow returns 200 on success.
	 *
	 * @covers ::unfollow
	 */
	public function test_unfollow_success() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_unfollow',
			function () {
				return true;
			}
		);

		$request = new WP_REST_Request( 'POST', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'unfollow' );
		$request->set_param( 'channel', 'home' );
		$request->set_param( 'url', 'https://example.com/feed' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test search returns 400 without query.
	 *
	 * @covers ::search
	 */
	public function test_search_missing_query() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'search' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test preview returns 400 without URL.
	 *
	 * @covers ::preview
	 */
	public function test_preview_missing_url() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'preview' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test unknown action returns 400.
	 *
	 * @covers ::get_items
	 */
	public function test_unknown_action() {
		wp_set_current_user( $this->user_id );

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'unknown_action' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test channel create returns new channel.
	 *
	 * @covers ::handle_channels_post
	 */
	public function test_channel_create() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_create_channel',
			function ( $result, $name ) {
				return array(
					'uid'  => 'new-channel',
					'name' => $name,
				);
			},
			10,
			2
		);

		$request = new WP_REST_Request( 'POST', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'channels' );
		$request->set_param( 'name', 'New Channel' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'new-channel', $data['uid'] );
		$this->assertEquals( 'New Channel', $data['name'] );
	}

	/**
	 * Test channel delete returns 200.
	 *
	 * @covers ::handle_channels_post
	 */
	public function test_channel_delete() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_delete_channel',
			function () {
				return true;
			}
		);

		$request = new WP_REST_Request( 'POST', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'channels' );
		$request->set_param( 'method', 'delete' );
		$request->set_param( 'channel', 'test-channel' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test timeline mark_read returns 200.
	 *
	 * @covers ::handle_timeline_post
	 */
	public function test_timeline_mark_read() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_timeline_mark_read',
			function () {
				return true;
			}
		);

		$request = new WP_REST_Request( 'POST', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'timeline' );
		$request->set_param( 'method', 'mark_read' );
		$request->set_param( 'channel', 'home' );
		$request->set_param( 'entry', array( 'entry-1', 'entry-2' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test channels are deduplicated by UID.
	 *
	 * @covers ::get_channels
	 */
	public function test_channels_deduplicated() {
		wp_set_current_user( $this->user_id );

		add_filter(
			'microsub_get_channels',
			function ( $channels ) {
				$channels[] = array(
					'uid'  => 'home',
					'name' => 'Home',
				);
				$channels[] = array(
					'uid'  => 'home',
					'name' => 'Home Duplicate',
				);
				$channels[] = array(
					'uid'  => 'other',
					'name' => 'Other',
				);
				return $channels;
			}
		);

		$request = new WP_REST_Request( 'GET', '/microsub/1.0/endpoint' );
		$request->set_param( 'action', 'channels' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data['channels'] );
	}
}
